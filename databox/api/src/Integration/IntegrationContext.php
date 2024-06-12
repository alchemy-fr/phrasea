<?php

namespace App\Integration;

enum IntegrationContext: string
{
    case AssetView = 'asset-view';
    case Basket = 'basket';
}
