<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface AssetActionIntegrationInterface extends IntegrationInterface
{
    public function handleAssetAction(string $action, Request $request, Asset $asset, array $options): Response;
}
