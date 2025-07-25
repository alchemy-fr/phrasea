<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class PhraseanetRenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const string METHOD_ENQUEUE = 'enqueue';
    final public const string METHOD_API = 'api';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $allowedMethods = [
            self::METHOD_API,
            self::METHOD_ENQUEUE,
        ];

        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${PHRASEANET_URL}')
                ->cannotBeEmpty()
                ->info('The Phraseanet base URL')
            ->end()
            ->arrayNode('renditions')
                ->isRequired()
                ->cannotBeEmpty()
                ->prototype('scalar')
                ->end()
            ->end()
            ->enumNode('method')
                ->isRequired()
                ->values($allowedMethods)
            ->end()
            ->integerNode('collectionId')
                ->info(sprintf('Required for the "%s" method', self::METHOD_ENQUEUE))
            ->end()
            ->integerNode('databoxId')
                ->info(sprintf('Required for the "%s" method', self::METHOD_API))
            ->end()
            ->scalarNode('token')
                ->defaultValue('${PHRASEANET_API_TOKEN}')
                ->cannotBeEmpty()
                ->info('The Phraseanet API key')
            ->end()
        ;
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $method = $config['method'];
        if (self::METHOD_API === $method && empty($config['databoxId'])) {
            throw new InvalidConfigurationException(sprintf('"databoxId" must be defined when using the "%s" method.', self::METHOD_API));
        }
        if (self::METHOD_ENQUEUE === $method && empty($config['collectionId'])) {
            throw new InvalidConfigurationException(sprintf('"collectionId" must be defined when using the "%s" method.', self::METHOD_ENQUEUE));
        }

        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        $info = [];

        if (self::METHOD_ENQUEUE === $config['method']) {
            $info[] = [
                'label' => 'Webhook URL',
                'description' => 'The URL to which Phraseanet will send incoming renditions.',
                'value' => $this->urlGenerator->generate('integration_phraseanet_webhook_event', [
                    'integrationId' => $config->getIntegrationId(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $info;
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        $actions = [
            self::METHOD_API => PhraseanetGenerateAssetRenditionsAction::class,
            self::METHOD_ENQUEUE => PhraseanetGenerateAssetRenditionsEnqueueMethodAction::class,
        ];

        $method = $config['method'];

        yield $firstJob = WorkflowHelper::createIntegrationJob(
            $config,
            $actions[$method],
            $method,
            ucfirst((string) $method),
        );

        foreach ($config['renditions'] as $rendition) {
            $receiptJob = WorkflowHelper::createIntegrationJob(
                $config,
                PhraseanetReceiveAction::class,
                PhraseanetReceiveAction::JOB_ID.':'.$rendition,
                $rendition,
            );

            $receiptJob->getNeeds()->append($firstJob->getId());

            yield $receiptJob;
        }
    }

    public static function getRenditionJobId(string $integrationId, string $renditionName): string
    {
        return self::getName().':'.$integrationId.':'.PhraseanetReceiveAction::JOB_ID.':'.$renditionName;
    }

    public static function getTitle(): string
    {
        return 'Phraseanet Renditions';
    }

    public static function getName(): string
    {
        return 'phraseanet.renditions';
    }
}
