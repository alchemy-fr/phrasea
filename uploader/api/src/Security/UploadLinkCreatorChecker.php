<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\UploadLink;
use App\Security\Acl\UploadLinkAclObject;

/**
 * An upload link acts on behalf of its creator: it only works while its creator could still create it.
 *
 * Nobody is signed in behind a link, so the creator is known by what was recorded of them when they last
 * saved it (id, groups, admin role): the permissions and the target are read now, Keycloak is not called.
 */
final readonly class UploadLinkCreatorChecker
{
    /**
     * The roles granting what the groups do not.
     */
    private const array KEPT_ROLES = [JwtUser::ROLE_ADMIN];

    public function __construct(
        private PermissionManager $permissionManager,
    ) {
    }

    public function snapshot(UploadLink $link, JwtUser $creator): void
    {
        $link->setCreatorGroups($creator->getGroups());
        $link->setCreatorRoles(array_intersect($creator->getRoles(), self::KEPT_ROLES));
    }

    /**
     * Whether the creator still has the `upload_link` permission and may still upload to the target of the link.
     */
    public function isStillAllowed(UploadLink $link): bool
    {
        if (in_array(JwtUser::ROLE_ADMIN, $link->getCreatorRoles(), true)) {
            return true;
        }

        $ownerId = (string) $link->getOwnerId();
        $creator = new JwtUser('', $ownerId, $ownerId, $link->getCreatorRoles(), $link->getCreatorGroups());

        return $this->permissionManager->isGranted($creator, new UploadLinkAclObject(), PermissionInterface::EDIT)
            && (bool) $link->getTarget()?->isAllowedFor($link->getCreatorGroups());
    }
}
