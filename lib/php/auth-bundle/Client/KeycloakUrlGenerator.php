<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

final readonly class KeycloakUrlGenerator
{
    public function __construct(
        private string $baseUrl,
        private string $realm,
    ) {
    }

    public function getLogoutUrl(string $clientId, string $redirectUri): string
    {
        return sprintf(
            '%s/logout?client_id=%s&post_logout_redirect_uri=%s',
            $this->getOpenIdConnectBaseUrl(),
            urlencode($clientId),
            urlencode($redirectUri)
        );
    }

    public function getUserinfoUrl(): string
    {
        return $this->getOpenIdConnectBaseUrl().'/userinfo';
    }

    public function getRealmInfo(): string
    {
        return $this->baseUrl.'/realms/'.$this->realm;
    }

    public function getTokenUrl(): string
    {
        return $this->getOpenIdConnectBaseUrl().'/token';
    }

    public function getAuthorizeUrl(string $clientId, string $redirectUri, string $state = ''): string
    {
        return $this->getOpenIdConnectBaseUrl().sprintf(
                '/auth?client_id=%s&response_type=code&redirect_uri=%s',
                urlencode($clientId),
                urlencode($redirectUri),
            ).(!empty($state) ? '&state='.urlencode($state) : '');
    }

    private function getOpenIdConnectBaseUrl(): string
    {
        return sprintf('%s/realms/%s/protocol/openid-connect',
            $this->baseUrl,
            $this->realm,
        );
    }

    public function getUsersApiUrl(): string
    {
        return $this->getAdminApiBaseUrl().'/users';
    }

    public function getGroupsApiUrl(): string
    {
        return $this->getAdminApiBaseUrl().'/groups';
    }

    private function getAdminApiBaseUrl(): string
    {
        return sprintf('%s/admin/realms/%s',
            $this->baseUrl,
            $this->realm,
        );
    }
}
