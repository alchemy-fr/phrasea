<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\OAuth;

class OAuthRegistry
{
    /**
     * @var array
     */
    private $oAuthProviders;
    /**
     * @var string
     */
    private $authBaseUrl;
    /**
     * @var string
     */
    private $authClientId;

    public function __construct(array $oAuthProviders, string $authBaseUrl, string $authClientId)
    {
        $this->oAuthProviders = $oAuthProviders;
        $this->authBaseUrl = $authBaseUrl;
        $this->authClientId = $authClientId;
    }

    public function getViewProviders(string $redirectUri): array
    {
        return array_map(function (array $provider) use ($redirectUri) {
            return [
                'title' => $provider['title'],
                'entrypoint' => sprintf(
                    '%s/oauth/%s/authorize?redirect_uri=%s&client_id=%s',
                        $this->authBaseUrl,
                        $provider['name'],
                        urlencode($redirectUri),
                        $this->authClientId,
                    ),
            ];
        }, $this->oAuthProviders);
    }
}
