<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\JwtUser;

/**
 * A workspace integration calling the API with its own key (see IntegrationAuthenticator): it acts as the owner
 * of the integration, with the roles and groups of the owner, but without any token of theirs.
 *
 * The operations it may reach (IntegrationAuthenticator::OPERATION_ACCESS) restrict it to the workspace of the integration.
 */
final class IntegrationUser extends JwtUser
{
    /**
     * @param string[] $roles
     * @param string[] $groups
     */
    public function __construct(
        public readonly string $integrationId,
        public readonly ?string $workspaceId,
        string $ownerId,
        string $ownerUsername,
        array $roles,
        array $groups,
    ) {
        parent::__construct('', $ownerId, $ownerUsername, $roles, $groups);
    }
}
