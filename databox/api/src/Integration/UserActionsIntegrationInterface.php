<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface UserActionsIntegrationInterface extends IntegrationInterface
{
    public function handleUserAction(string $action, Request $request, IntegrationConfig $config): ?Response;
}
