<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Auth;

class IdentityProvidersRegistry
{
    /**
     * @var array
     */
    private $identityProviders;
    /**
     * @var string
     */
    private $authBaseUrl;
    /**
     * @var string
     */
    private $authClientId;

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
                        $provider['type'],
                        $provider['name'],
                        urlencode($redirectUri),
                        $this->authClientId,
                    ),
            ];
        }, $this->identityProviders);
    }
}
