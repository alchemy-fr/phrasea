<?php

declare(strict_types=1);

namespace App\Integration;

enum IntegrationContext: string
{
    case AssetView = 'asset-view';
    case Basket = 'basket';
}
