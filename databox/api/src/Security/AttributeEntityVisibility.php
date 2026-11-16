<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Which values of the entity lists a collection listing returns, on either engine (ORM or Elasticsearch),
 * on top of the workspace and list filters.
 *
 * Either every value of the lists in $listIds (whatever their status), or:
 * the approved values, those created by $userId, and the pending values of the workspaces in $pendingWorkspaceIds.
 *
 * @see AttributeEntityVisibilityResolver
 */
final readonly class AttributeEntityVisibility
{
    /**
     * @param list<string>|null $listIds             the lists whose values are all visible, null when the rule below applies
     * @param list<string>      $pendingWorkspaceIds
     */
    public function __construct(
        public ?array $listIds = null,
        public ?string $userId = null,
        public array $pendingWorkspaceIds = [],
    ) {
    }
}
