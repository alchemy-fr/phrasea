<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ActionsIntegrationInterface extends IntegrationInterface
{
    public function handleAction(string $action, Request $request, IntegrationConfig $config): ?Response;
}
