<?php

declare(strict_types=1);

namespace App\Api\Traits;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * For the requests sent to the Keycloak admin API with the token of the current user.
 */
trait KeycloakTokenOwnerTrait
{
    use SecurityAwareTrait;

    /**
     * The current user, holding the Keycloak role itself. The operation security is not enough:
     * AdminVoter grants every attribute to the admins. And an admin acting as another user does
     * not act with its own Keycloak token (the impersonated user has none of these roles).
     */
    private function getKeycloakTokenOwner(string $role): JwtUser
    {
        $user = $this->getStrictUser();
        if ($user->isImpersonated() || !in_array($role, $user->getRoles(), true)) {
            throw new AccessDeniedHttpException(sprintf('Missing the role "%s"', $role));
        }

        return $user;
    }
}
