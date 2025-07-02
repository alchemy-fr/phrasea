<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Uploader;

use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class UploaderIntegration extends AbstractIntegration
{
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
            ->end()
            ->scalarNode('securityToken')
                ->cannotBeEmpty()
                ->info('The security token to authenticate Uploader requests')
            ->end()
        ;
    }

    public function generateConfigurationDefaults(array $userConfig): array
    {
        $userConfig['securityToken'] ??= bin2hex(random_bytes(32));

        return $userConfig;
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

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
                'label' => 'Security Token',
                'description' => 'The security token to set in the Uploader Target configuration to authenticate requests.',
                'value' => $config['securityToken'] ?? 'Not set',
            ],
        ];
    }

    public static function getTitle(): string
    {
        return 'Uploader';
    }

    public static function getName(): string
    {
        return 'phrasea.uploader';
    }
}
