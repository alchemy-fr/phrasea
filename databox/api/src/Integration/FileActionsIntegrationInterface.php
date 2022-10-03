<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FileActionsIntegrationInterface extends IntegrationInterface
{
    public function handleFileAction(string $action, Request $request, File $file, array $options): Response;

    public function supportsFileActions(File $file, array $options): bool;
}
