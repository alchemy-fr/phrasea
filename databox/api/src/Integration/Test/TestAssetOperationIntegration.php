<?php

declare(strict_types=1);

namespace App\Integration\Test;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AssetOperationIntegrationInterface;

class TestAssetOperationIntegration implements AssetOperationIntegrationInterface
{
    private const VERSION = '1.0';
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(BatchAttributeManager $batchAttributeManager)
    {
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function handleAsset(WorkspaceIntegration $workspaceIntegration, Asset $asset): void
    {
        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->originVendor = self::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.self::VERSION;
        $i->name = 'test';
        $i->confidence = 0.42;
        $i->value = sprintf('Test value coming from "%s" integration (version %s)', self::getName(), self::VERSION);
        $input->actions[] = $i;

        $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input);
    }

    public static function getName(): string
    {
        return 'Test asset operation';
    }
}
