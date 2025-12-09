<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

final readonly class KeycloakUrlGenerator
{
    public function __construct(
        private string $baseUrl,
        private ?string $internalBaseUrl,
        private string $realm,
    ) {
    }

    public function getLogoutUrl(?string $clientId = null, ?string $redirectUri = null): string
    {
        if (null === $clientId) {
            return $this->getOpenIdConnectBaseUrl().'/logout';
        }

        return sprintf(
            '%s/logout?client_id=%s&post_logout_redirect_uri=%s',
            $this->getOpenIdConnectBaseUrl(false),
            urlencode($clientId),
            urlencode($redirectUri)
        );
    }

    public function getUserinfoUrl(): string
    {
        return $this->getOpenIdConnectBaseUrl().'/userinfo';
    }

    public function getRealmInfoUrl(bool $internal): string
    {
        return $this->getBaseUrl($internal).'/realms/'.$this->realm;
    }

    private function getBaseUrl(bool $internal): string
    {
        if (!$internal || !$this->internalBaseUrl) {
            return $this->baseUrl;
        }

        return $this->internalBaseUrl;
    }

    public function getTokenUrl(): string
    {
        return $this->getOpenIdConnectBaseUrl().'/token';
    }

    public function getAuthorizeUrl(string $clientId, string $redirectUri, string $state = ''): string
    {
        return $this->getOpenIdConnectBaseUrl(false).sprintf(
            '/auth?client_id=%s&response_type=code&redirect_uri=%s',
            urlencode($clientId),
            urlencode($redirectUri),
        ).(!empty($state) ? '&state='.urlencode($state) : '');
    }

    private function getOpenIdConnectBaseUrl(bool $internal = true): string
    {
        return sprintf('%s/realms/%s/protocol/openid-connect',
            $this->getBaseUrl($internal),
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
            $this->getBaseUrl(true),
            $this->realm,
        );
    }
}
