<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class PhraseanetRenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const METHOD_ENQUEUE = 'enqueue';
    final public const METHOD_API = 'api';

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
                ->defaultValue('${PHRASEANET_BASE_URL}')
                ->cannotBeEmpty()
                ->info('The Phraseanet base URL')
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

    public function validateConfiguration(array $config): void
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

    public function getConfigurationInfo(array $config): array
    {
        $info = [];

        if (self::METHOD_ENQUEUE === $config['method']) {
            $info['Webhook URL'] = $this->urlGenerator->generate('integration_phraseanet_webhook_event', [
                'integrationId' => $config['integrationId'],
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $info;
    }

    public function getWorkflowJobDefinitions(array $config, Workflow $workflow): iterable
    {
        $actions = [
            self::METHOD_API => PhraseanetGenerateAssetRenditionsAction::class,
            self::METHOD_ENQUEUE => PhraseanetGenerateAssetRenditionsEnqueueMethodAction::class,
        ];

        $method = $config['method'];

        yield WorkflowHelper::createIntegrationJob(
            $config,
            $actions[$method],
            $method,
            ucfirst((string) $method),
        );
    }

    public static function getTitle(): string
    {
        return 'Phraseanet renditions';
    }

    public static function getName(): string
    {
        return 'phraseanet.renditions';
    }
}
