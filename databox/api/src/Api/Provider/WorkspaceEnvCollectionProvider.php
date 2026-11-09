<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Entity\Integration\WorkspaceEnv;

final class WorkspaceEnvCollectionProvider extends AbstractWorkspaceEnvCollectionProvider
{
    protected function getEntityClass(): string
    {
        return WorkspaceEnv::class;
    }
}
