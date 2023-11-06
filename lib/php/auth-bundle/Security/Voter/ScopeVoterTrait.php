<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use Alchemy\AuthBundle\Security\Token\JwtToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trait ScopeVoterTrait
{
    public function hasScope(string $scope, TokenInterface $token): bool
    {
        if ($token instanceof JwtToken) {
            return $token->hasScope($scope);
        }

        return false;
    }
}
