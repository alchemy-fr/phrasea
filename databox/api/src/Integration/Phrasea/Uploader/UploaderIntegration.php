<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Uploader;

use App\Integration\AbstractIntegration;
use App\Integration\Config\ConfigWidget;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

#[AsTaggedItem(index: self::NAME)]
class UploaderIntegration extends AbstractIntegration
{
    public const string NAME = 'phrasea.uploader';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${UPLOADER_API_URL}')
                ->cannotBeEmpty()
                ->info('The Uploader API base URL')
            ->end()
            ->scalarNode('collectionId')
                ->info('The collection target')
                ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Collection->value)
            ->end()
            ->scalarNode('securityKey')
                ->cannotBeEmpty()
                ->info('The security key to authenticate Uploader requests')
                ->attribute(ConfigWidget::ATTR_SECRET, true)
            ->end()
        ;
    }

    #[\Override]
    public function generateConfigurationDefaults(array $userConfig): array
    {
        $userConfig['securityKey'] ??= bin2hex(random_bytes(32));

        return $userConfig;
    }

    /**
     * A security key must not be shared by two integrations.
     */
    #[\Override]
    public function prepareDuplicateConfiguration(array $config): array
    {
        unset($config['securityKey']);

        return $this->generateConfigurationDefaults($config);
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    #[\Override]
    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        return [
            [
                'label' => 'Target URL',
                'description' => 'The URL to which the Uploader will send incoming commits.',
                'value' => $this->urlGenerator->generate('integration_uploader_incoming_commit', [
                    'integrationId' => $config->getIntegrationId(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'label' => 'Authorization Scheme',
                'description' => 'Use this Authorization header scheme to authenticate requests from Uploader.',
                'value' => 'ApiKey',
            ],
            [
                'label' => 'Security Key',
                'description' => 'The security key to set in the Uploader Target configuration to authenticate requests.',
                'value' => $config['securityKey'] ?? 'Not set',
            ],
        ];
    }

    public static function getDisplayName(): string
    {
        return 'Uploader';
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Ingest,
        ];
    }

    public static function getName(): string
    {
        return self::NAME;
    }
}
