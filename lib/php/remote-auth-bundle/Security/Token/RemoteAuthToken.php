<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class RemoteAuthToken extends AbstractToken
{
    private array $scopes = [];

    public function __construct(
        private readonly string $accessToken,
        private string $refreshToken,
        array $roles = []
    )
    {
        parent::__construct($roles);
        $this->setAttribute('rt', $this->refreshToken);
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

    public function getRefreshToken(): string
    {
        return $this->getAttribute('rt');
    }
}
