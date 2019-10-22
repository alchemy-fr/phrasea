<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class RemoteAuthToken extends AbstractToken
{
    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var array
     */
    private $scopes = [];
    /**
     * @var string
     */
    private $providerKey;

    public function __construct(string $providerKey, string $accessToken, array $roles = [])
    {
        parent::__construct($roles);
        $this->accessToken = $accessToken;
        $this->providerKey = $providerKey;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getCredentials()
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

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }
}
