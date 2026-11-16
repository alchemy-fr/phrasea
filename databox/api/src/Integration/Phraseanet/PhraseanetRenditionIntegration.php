<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\Config\ConfigWidget;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

#[AsTaggedItem(index: self::NAME)]
class PhraseanetRenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public const string NAME = 'phraseanet.renditions';

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
                ->attribute(ConfigWidget::ATTR_URL, true)
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
                ->attribute(ConfigWidget::ATTR_SECRET, true)
            ->end()
            ->scalarNode('webhookSecret')
                ->cannotBeEmpty()
                ->info(sprintf('The secret carried by the Webhook URL set in Phraseanet ("%s" method): events without it are refused', self::METHOD_ENQUEUE))
                ->attribute(ConfigWidget::ATTR_SECRET, true)
            ->end()
        ;
    }

    #[\Override]
    public function generateConfigurationDefaults(array $userConfig): array
    {
        $userConfig['webhookSecret'] ??= bin2hex(random_bytes(32));

        return $userConfig;
    }

    /**
     * A webhook secret must not be shared by two integrations.
     */
    #[\Override]
    public function prepareDuplicateConfiguration(array $config): array
    {
        unset($config['webhookSecret']);

        return $this->generateConfigurationDefaults($config);
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

    #[\Override]
    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        $info = [];

        if (self::METHOD_ENQUEUE === $config['method']) {
            $secret = (string) $config->getClientValue('webhookSecret');
            $isReference = str_contains($secret, '$');
            $url = '' === $secret ? 'Not set' : $this->urlGenerator->generate('integration_phraseanet_webhook_event', [
                'integrationId' => $config->getIntegrationId(),
                'token' => $secret,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            $info[] = [
                'label' => 'Webhook URL',
                'description' => match (true) {
                    '' === $secret => 'Phraseanet events are refused until a "webhookSecret" is set in the configuration.',
                    $isReference => sprintf('The URL to which Phraseanet will send incoming renditions, its "token" being the value of %s (not shown). It carries the webhook secret: keep it private.', $secret),
                    default => 'The URL to which Phraseanet will send incoming renditions. It carries the webhook secret: keep it private.',
                },
                'value' => $isReference ? strtr($url, [rawurlencode($secret) => $secret]) : $url,
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

    public static function getDisplayName(): string
    {
        return 'Phraseanet Renditions';
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Processing,
        ];
    }

    public static function getName(): string
    {
        return self::NAME;
    }
}
