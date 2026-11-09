<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Entity\Integration\WorkspaceSecret;

final class WorkspaceSecretCollectionProvider extends AbstractWorkspaceEnvCollectionProvider
{
    protected function getEntityClass(): string
    {
        return WorkspaceSecret::class;
    }
}
