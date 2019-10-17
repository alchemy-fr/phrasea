<?php

declare(strict_types=1);

namespace App\OAuth;

use App\Entity\OAuthClient;
use OAuth2\Model\IOAuth2Client;
use OAuth2\OAuth2;
use OAuth2\OAuth2AuthenticateException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientAllowedScopesOAuth2 extends OAuth2
{
    const NO_SCOPE_PROVIDED = '__NO_SCOPE_PROVIDED__';

    /**
     * @inheritDoc
     */
    public function grantAccessToken(Request $request = null)
    {
        $overriddenRequest = clone $request;

        if (empty(trim($overriddenRequest->request->get('scope', '')))) {
            // Because we don't we do grant all scopes
            $overriddenRequest->request->set('scope', self::NO_SCOPE_PROVIDED);
        }

        return parent::grantAccessToken($overriddenRequest);
    }

    private function validateClientAllowedScopes(OAuthClient $client, array $scopes): void
    {
        $allowedScopes = $client->getAllowedScopes();
        foreach ($scopes as $scope) {
            if (!in_array($scope, $allowedScopes)) {
                throw new OAuth2AuthenticateException(
                    Response::HTTP_BAD_REQUEST,
                    'Bearer',
                    'Service',
                    OAuth2::ERROR_INVALID_SCOPE,
                    sprintf('Scope "%s" is not allowed for this client.', $scope)
                );
            }
        }
    }

    public function createAccessToken(IOAuth2Client $client, $data, $scope = null, $access_token_lifetime = null, $issue_refresh_token = true, $refresh_token_lifetime = null)
    {
        if (!empty(trim((string) $scope))) {
            $scopes = explode(' ', $scope);
            $scopes = array_filter($scopes, function (string $scope): bool {
                return $scope !== self::NO_SCOPE_PROVIDED;
            });

            if (!empty($scopes) && $client instanceof OAuthClient) {
                $this->validateClientAllowedScopes($client, $scopes);
            }

            $scope = implode(' ', $scopes);
        }

        return parent::createAccessToken($client, $data, $scope, $access_token_lifetime, $issue_refresh_token, $refresh_token_lifetime);
    }

    /**
     * @inheritDoc
     */
    protected function checkScope($requiredScope, $availableScope)
    {
        if ($requiredScope === self::NO_SCOPE_PROVIDED) {
            return true;
        }

        // The required scope should match or be a subset of the available scope
        if (!is_array($requiredScope)) {
            $requiredScope = explode(' ', trim((string)$requiredScope));
        }

        if (!is_array($availableScope)) {
            $availableScope = explode(' ', trim((string)$availableScope));
        }

        return 0 === count(array_diff($requiredScope, $availableScope));
    }
}
