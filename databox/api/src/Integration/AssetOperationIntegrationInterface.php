<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;

interface AssetOperationIntegrationInterface extends IntegrationInterface
{
    public function handleAsset(WorkspaceIntegration $workspaceIntegration, Asset $asset): void;
}
