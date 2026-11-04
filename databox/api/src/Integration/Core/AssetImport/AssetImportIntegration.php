<?php

declare(strict_types=1);

namespace App\Integration\Core\AssetImport;

use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Integration\AbstractIntegration;
use App\Integration\Config\ConfigWidget;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Exposes a webhook receiving import instructions (JSON or NDJSON), authenticated by an API key.
 */
#[AsTaggedItem(index: self::NAME)]
class AssetImportIntegration extends AbstractIntegration
{
    public const string NAME = 'core.asset_import';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('securityKey')
                ->cannotBeEmpty()
                ->info('The key to send in the "Authorization: ApiKey <key>" header of the webhook requests')
                ->attribute(ConfigWidget::ATTR_SECRET, true)
            ->end()
            ->scalarNode('collectionId')
                ->info('The collection receiving the imported assets (the workspace root otherwise)')
                ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Collection->value)
            ->end()
            ->integerNode('privacy')
                ->min(WorkspaceItemPrivacyInterface::SECRET)
                ->max(WorkspaceItemPrivacyInterface::PUBLIC)
                ->info('The privacy of the imported assets which do not set one (0: secret … 5: public)')
                ->example((string) WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE)
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

    #[\Override]
    public function validateConfiguration(IntegrationConfig $config): void
    {
        $collectionId = $config['collectionId'] ?? null;
        if (null === $collectionId || '' === $collectionId) {
            return;
        }

        $collection = Uuid::isValid((string) $collectionId) ? $this->em->find(Collection::class, $collectionId) : null;
        if (!$collection instanceof Collection || $collection->getWorkspaceId() !== $config->getWorkspaceId()) {
            $e = new InvalidConfigurationException(sprintf('Collection "%s" not found in the workspace', $collectionId));
            $e->setPath('root.collectionId');

            throw $e;
        }
    }

    #[\Override]
    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        return [
            [
                'label' => 'Webhook URL',
                'description' => 'The URL receiving the import instructions (POST).',
                'value' => $this->urlGenerator->generate('integration_asset_import_webhook', [
                    'integrationId' => $config->getIntegrationId(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'label' => 'Authorization Scheme',
                'description' => 'Authenticate the requests with the "Authorization: ApiKey <Security Key>" header.',
                'value' => 'ApiKey',
            ],
            [
                'label' => 'Security Key',
                'description' => 'The key of the Authorization header.',
                'value' => $config['securityKey'] ?? 'Not set',
            ],
            [
                'label' => 'Formats',
                'description' => 'The Content-Type of the request body: one entry per line (NDJSON) or a JSON list of entries.',
                'value' => 'application/x-ndjson, application/json',
            ],
        ];
    }

    public static function getDisplayName(): string
    {
        return 'Asset import';
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
