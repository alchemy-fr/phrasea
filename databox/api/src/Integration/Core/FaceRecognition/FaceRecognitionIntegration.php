<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\Action\UserActionsTrait;
use App\Integration\Config\RenditionConfigNormalizerTrait;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionAnalyze;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionPropagate;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\FilterNeedIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\UserActionsIntegrationInterface;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Notification\EntityDisableNotifyableException;
use App\Security\Voter\AbstractVoter;
use App\Service\Storage\RenditionManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Self-hosted face detection and recognition (see infra/docker/face-recognition).
 */
class FaceRecognitionIntegration extends AbstractIntegration implements FilterNeedIntegrationInterface, WorkflowIntegrationInterface, UserActionsIntegrationInterface
{
    use RenditionConfigNormalizerTrait;

    use UserActionsTrait;

    final public const string VERSION = '1.0';

    final public const string ACTION_ANALYZE = 'analyze';
    final public const string ACTION_IDENTIFY = 'identify';

    final public const string DEFAULT_RENDITION = 'preview';
    final public const float DEFAULT_MIN_CONFIDENCE = 0.5;
    final public const float DEFAULT_MATCH_THRESHOLD = 0.5;
    final public const int DEFAULT_MAX_IMAGE_SIZE = 2048;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly RenditionManager $renditionManager,
        private readonly FaceRecognitionAnalyzer $analyzer,
    ) {
    }

    public static function getName(): string
    {
        return 'core.face_recognition';
    }

    public static function getDisplayName(): string
    {
        return 'Face Recognition';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('rendition')
                ->defaultValue(self::DEFAULT_RENDITION)
                ->info('Rendition sent to the face recognition service (must be an image). Falls back to the source file when the rendition is missing')
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
                ->info('Minimum cosine similarity with a face identified by a user to recognize the same person (0 disables recognition)')
            ->end()
            ->booleanNode('autoPropagate')
                ->defaultTrue()
                ->info('When a user identifies a face, apply the identity to the similar faces of the workspace')
            ->end()
            ->scalarNode('attribute')
                ->defaultNull()
                ->info('Slug of a multi-valued string attribute receiving the names of the recognized persons')
            ->end()
        ;
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
            self::ACTION_IDENTIFY => $this->handleIdentify($request, $config),
            default => throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action)),
        };
    }

    private function handleAnalyze(Request $request, IntegrationConfig $config): ?Response
    {
        $asset = $this->getAsset($request->request->get('assetId'), $config);

        $this->bus->dispatch(new FaceRecognitionAnalyze($asset->getId(), $config->getIntegrationId()));

        return null;
    }

    private function handleIdentify(Request $request, IntegrationConfig $config): Response
    {
        $faceId = $request->request->get('faceId');
        if (empty($faceId)) {
            throw new BadRequestHttpException('Missing faceId');
        }

        $face = DoctrineUtil::findStrict($this->em, AssetFace::class, $faceId);
        $asset = $this->getAsset($face->getAsset()->getId(), $config);

        $identity = $request->request->get('identity');
        $summary = $this->analyzer->identify($face, null !== $identity ? (string) $identity : null, $config);

        if ($config['autoPropagate']) {
            $this->bus->dispatch(new FaceRecognitionPropagate($face->getId(), $config->getIntegrationId()));
        }

        return new JsonResponse([
            'asset' => $asset->getId(),
            ...$summary,
        ]);
    }

    private function getAsset(?string $assetId, IntegrationConfig $config): Asset
    {
        if (empty($assetId)) {
            throw new BadRequestHttpException('Missing assetId');
        }

        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
        if ($asset->getWorkspaceId() !== $config->getWorkspaceId()) {
            throw new BadRequestHttpException(sprintf('Asset "%s" does not belong to the integration workspace', $assetId));
        }
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);

        return $asset;
    }

    #[\Override]
    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationConfig $config): array
    {
        return [
            'processIncoming' => $config['processIncoming'],
            'matchThreshold' => $config['matchThreshold'],
            'autoPropagate' => $config['autoPropagate'],
            'attribute' => $config['attribute'],
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
}
