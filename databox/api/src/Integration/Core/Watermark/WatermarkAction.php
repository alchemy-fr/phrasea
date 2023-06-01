<?php

declare(strict_types=1);

namespace App\Integration\Core\Watermark;

use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

class WatermarkAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct()
    {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
