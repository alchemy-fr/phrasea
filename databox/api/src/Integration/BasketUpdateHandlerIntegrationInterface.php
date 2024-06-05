<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;

interface BasketUpdateHandlerIntegrationInterface extends IntegrationInterface
{
    public function handleBasketUpdate(IntegrationData $data, IntegrationConfig $config): void;
}
