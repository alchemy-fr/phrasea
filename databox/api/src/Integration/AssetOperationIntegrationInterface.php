<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;

/**
 * @deprecated you should use WorkflowIntegrationInterface
 *
 * @see        WorkflowIntegrationInterface
 */
interface AssetOperationIntegrationInterface extends IntegrationInterface
{
    public function handleAsset(Asset $asset, array $config): void;

    public function supportsAsset(Asset $asset, array $config): bool;
}
