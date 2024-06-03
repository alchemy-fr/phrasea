<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Basket\Basket;
use App\Entity\Integration\IntegrationBasketData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface BasketActionsIntegrationInterface extends IntegrationInterface
{
    public function handleBasketAction(string $action, Request $request, Basket $basket, IntegrationConfig $config): ?Response;

    public function handleBasketUpdate(IntegrationBasketData $data, IntegrationConfig $config): void;
}
