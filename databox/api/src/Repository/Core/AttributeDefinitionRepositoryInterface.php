<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttributeDefinition;
use Doctrine\Persistence\ObjectRepository;

interface AttributeDefinitionRepositoryInterface extends ObjectRepository
{
    public const OPT_TYPES = 'types';
    public const OPT_FACET_ENABLED = 'facet_enabled';

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?string $userId, array $groupIds, array $options = []): array;

    public function findByKey(string $key, string $workspaceId): ?AttributeDefinition;

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceFallbackDefinitions(string $workspaceId): array;

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceInitializeDefinitions(string $workspaceId): array;

    /**
     * @return AttributeDefinition[]
     */
    public function getWorkspaceDefinitions(string $workspaceId): array;
}
