<?php

declare(strict_types=1);

namespace App\Integration\Core\Test;

use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TestAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(private readonly BatchAttributeManager $batchAttributeManager)
    {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->originVendor = TestAssetOperationIntegration::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.TestAssetOperationIntegration::VERSION;
        $i->name = $config['attribute'];
        $i->confidence = 0.42;
        $i->value = sprintf('Test value coming from "%s" integration (version %s)', TestAssetOperationIntegration::getName(), TestAssetOperationIntegration::VERSION);
        $input->actions[] = $i;

        try {
            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );
        } catch (BadRequestHttpException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
