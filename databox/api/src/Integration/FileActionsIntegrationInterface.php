<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FileActionsIntegrationInterface extends IntegrationInterface
{
    final public const DATA_FILE_ID = 'file_id';
    final public const DATA_FILE = 'file';

    public function handleFileAction(string $action, Request $request, File $file, array $config): Response;

    public function supportsFileActions(File $file, array $config): bool;
}
