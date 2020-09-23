<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Auth;

class IdentityProvidersRegistry
{
    private array $identityProviders;
    private string $authBaseUrl;
    private string $authClientId;

    public function __construct(array $identityProviders, string $authBaseUrl, string $authClientId)
    {
        $this->identityProviders = $identityProviders;
        $this->authBaseUrl = $authBaseUrl;
        $this->authClientId = $authClientId;
    }

    public function getViewProviders(string $redirectUri): array
    {
        return array_map(function (array $provider) use ($redirectUri) {
            return [
                'title' => $provider['title'],
                'entrypoint' => sprintf(
                    '%s/%s/%s/authorize?redirect_uri=%s&client_id=%s',
                    $this->authBaseUrl,
                    urlencode($provider['type']),
                    urlencode($provider['name']),
                    urlencode($redirectUri),
                    urlencode($this->authClientId),
                ),
            ];
        }, $this->identityProviders);
    }
}
