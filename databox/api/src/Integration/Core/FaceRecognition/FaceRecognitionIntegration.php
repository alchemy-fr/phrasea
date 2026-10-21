<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\Action\UserActionsTrait;
use App\Integration\Config\ConfigWidget;
use App\Integration\Config\RenditionConfigNormalizerTrait;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionAnalyze;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\FilterNeedIntegrationInterface;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\UserActionsIntegrationInterface;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Notification\EntityDisableNotifyableException;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Storage\RenditionManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Self-hosted face detection and recognition (see infra/docker/face-recognition).
 *
 * The persons are the entities of a list of the workspace, the faces are the values of an entity
 * attribute bound to that list (one value per detected face, with its box and embedding).
 */
#[AsTaggedItem(index: self::NAME)]
class FaceRecognitionIntegration extends AbstractIntegration implements FilterNeedIntegrationInterface, WorkflowIntegrationInterface, UserActionsIntegrationInterface
{
    use RenditionConfigNormalizerTrait;

    use UserActionsTrait;

    public const string NAME = 'core.face_recognition';

    final public const string VERSION = '1.0';

    final public const string ACTION_ANALYZE = 'analyze';

    final public const string DEFAULT_RENDITION = 'preview';
    final public const float DEFAULT_MIN_CONFIDENCE = 0.5;
    final public const float DEFAULT_MATCH_THRESHOLD = 0.5;
    final public const int DEFAULT_MAX_IMAGE_SIZE = 2048;
    final public const string DEFAULT_UNKNOWN_LABEL = 'Unknown person';

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RenditionManager $renditionManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public static function getDisplayName(): string
    {
        return 'Face Recognition';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        ConfigWidget::apply($builder->scalarNode('attribute'), ConfigWidget::Attribute, [
            'type' => EntityAttributeType::NAME,
            'multiple' => true,
            'entityList' => true,
        ])
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Slug of a multi-valued attribute of type "entity": each detected face is a value of it (person, box and confidence), the persons are the entities of its list (the look-alikes not named yet are pending entities)')
            ->end()
            ->scalarNode('unknownLabel')
                ->defaultValue(self::DEFAULT_UNKNOWN_LABEL)
                ->cannotBeEmpty()
                ->info('Name given to a person nobody has named yet (a short code is appended)')
            ->end()
            ->scalarNode('rendition')
                ->defaultValue(self::DEFAULT_RENDITION)
                ->info('Rendition sent to the face recognition service (must be an image). Falls back to the source file when the rendition is missing')
                ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Rendition->value)
            ->end()
            ->integerNode('maxImageSize')
                ->defaultValue(self::DEFAULT_MAX_IMAGE_SIZE)
                ->min(256)
                ->info('Images are downscaled to this size (pixels, longest side) before being sent to the service')
            ->end()
            ->booleanNode('processIncoming')
                ->defaultFalse()
                ->info('Detect faces on all incoming assets automatically')
            ->end()
            ->floatNode('minConfidence')
                ->defaultValue(self::DEFAULT_MIN_CONFIDENCE)
                ->min(0)->max(1)
                ->info('Minimum detection confidence for a face to be kept')
            ->end()
            ->floatNode('matchThreshold')
                ->defaultValue(self::DEFAULT_MATCH_THRESHOLD)
                ->min(0)->max(1)
                ->info('Minimum cosine similarity with a known face to recognize the same person (0 disables recognition: every face is a new unknown person)')
            ->end()
            ->booleanNode('autoPropagate')
                ->defaultTrue()
                ->info('When a user names a face, apply the person to the similar faces of the workspace')
            ->end()
        ;
    }

    #[\Override]
    public function validateConfiguration(IntegrationConfig $config): void
    {
        $workspaceId = $config->getWorkspaceId();
        if (null === $workspaceId) {
            throw new InvalidConfigurationException('Face recognition requires a workspace');
        }

        $definition = $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($workspaceId, (string) $config['attribute']);
        if (null === $definition) {
            throw self::createAttributeException(sprintf('Attribute "%s" not found in the workspace', $config['attribute']));
        }
        if (EntityAttributeType::NAME !== $definition->getType()) {
            throw self::createAttributeException(sprintf('Attribute "%s" must be of type "entity"', $config['attribute']));
        }
        if (!$definition->isMultiple()) {
            throw self::createAttributeException(sprintf('Attribute "%s" must be multi-valued', $config['attribute']));
        }
        if (null === $definition->getEntityList()) {
            throw self::createAttributeException(sprintf('Attribute "%s" must be bound to an entity list', $config['attribute']));
        }
    }

    private static function createAttributeException(string $message): InvalidConfigurationException
    {
        $e = new InvalidConfigurationException($message);
        $e->setPath('root.attribute');

        return $e;
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        if (!$workflow->getOn()->hasEventName(AssetIngestWorkflowEvent::EVENT)) {
            return [];
        }

        if (!$config['processIncoming']) {
            return [];
        }

        yield WorkflowHelper::createIntegrationJob(
            $config,
            FaceRecognitionDetectAction::class,
        );
    }

    public function getNeededJobs(IntegrationConfig $config, IntegrationConfig $neededIntegrationConfig, Job $job): ?array
    {
        $rendition = $config['rendition'] ?? null;
        if (!$rendition) {
            return null;
        }

        if ($neededIntegrationConfig->getIntegration() instanceof RenditionIntegration) {
            try {
                $renditionDefinition = $this->renditionManager
                    ->getRenditionDefinitionByName($neededIntegrationConfig->getWorkspaceId(), $rendition);
            } catch (\InvalidArgumentException $e) {
                throw new EntityDisableNotifyableException($config->getWorkspaceIntegration(), sprintf('Rendition "%s" not found', $rendition), sprintf('Rendition "%s" not found in workspace "%s"', $rendition, $neededIntegrationConfig->getWorkspaceIntegration()->getWorkspace()->getName()), $e->getCode(), $e);
            }

            return [
                RenditionIntegration::getJobId(
                    $neededIntegrationConfig,
                    $renditionDefinition->getId(),
                ),
            ];
        }

        return null;
    }

    public function handleUserAction(string $action, Request $request, IntegrationConfig $config): ?Response
    {
        return match ($action) {
            self::ACTION_ANALYZE => $this->handleAnalyze($request, $config),
            default => throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action)),
        };
    }

    private function handleAnalyze(Request $request, IntegrationConfig $config): ?Response
    {
        $assetId = $request->request->get('assetId');
        if (empty($assetId)) {
            throw new BadRequestHttpException('Missing assetId');
        }

        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
        if ($asset->getWorkspaceId() !== $config->getWorkspaceId()) {
            throw new BadRequestHttpException(sprintf('Asset "%s" does not belong to the integration workspace', $assetId));
        }
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);

        $this->bus->dispatch(new FaceRecognitionAnalyze($asset->getId(), $config->getIntegrationId()));

        return null;
    }

    #[\Override]
    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationConfig $config): array
    {
        $definition = null !== $config->getWorkspaceId() ? $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($config->getWorkspaceId(), (string) $config['attribute']) : null;

        return [
            'attribute' => $config['attribute'],
            'attributeDefinition' => $definition?->getId(),
            'entityList' => $definition?->getEntityList()?->getId(),
            'processIncoming' => $config['processIncoming'],
            'matchThreshold' => $config['matchThreshold'],
            'autoPropagate' => $config['autoPropagate'],
        ];
    }

    #[\Override]
    public function getSupportedContexts(): array
    {
        return [IntegrationContext::AssetView];
    }

    protected function getRenditionConfigPaths(): array
    {
        return ['rendition'];
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Ai,
        ];
    }
}
