<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class JwtToken extends AbstractToken
{
    private array $scopes = [];

    public function __construct(
        private readonly string $accessToken,
        array $roles = []
    )
    {
        parent::__construct($roles);
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes);
    }
}
