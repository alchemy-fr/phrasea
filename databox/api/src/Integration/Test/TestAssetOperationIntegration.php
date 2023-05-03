<?php

declare(strict_types=1);

namespace App\Integration\Test;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegration;
use App\Integration\AssetOperationIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class TestAssetOperationIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface
{
    private const VERSION = '1.0';

    public function __construct(private readonly BatchAttributeManager $batchAttributeManager)
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('attribute')
                ->defaultValue('test')
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function handleAsset(Asset $asset, array $config): void
    {
        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->originVendor = self::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.self::VERSION;
        $i->name = $config['attribute'];
        $i->confidence = 0.42;
        $i->value = sprintf('Test value coming from "%s" integration (version %s)', self::getName(), self::VERSION);
        $input->actions[] = $i;

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $input,
            null
        );
    }

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return null !== $asset->getSource();
    }

    public static function getName(): string
    {
        return 'test.asset_operation';
    }

    public static function getTitle(): string
    {
        return 'Test asset operation';
    }
}
