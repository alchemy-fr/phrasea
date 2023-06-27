<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\OAuth;

use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use OAuth2\Model\IOAuth2Client;
use OAuth2\OAuth2;
use OAuth2\OAuth2AuthenticateException;
use OAuth2\OAuth2ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides default OAuth2 service in order to keep requested scopes empty.
 * The original service was setting all supported scopes to the grant request if no scope were required.
 */
class ClientAllowedScopesOAuth2 extends OAuth2
{
    final public const NO_SCOPE_PROVIDED = '__NO_SCOPE_PROVIDED__';

    /**
     * Overrides parent method in order to keep "scope" empty if input param is empty.
     *
     * {@inheritdoc}
     */
    public function grantAccessToken(Request $request = null)
    {
        $filters = [
            'grant_type' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => ['regexp' => self::GRANT_TYPE_REGEXP],
                'flags' => FILTER_REQUIRE_SCALAR,
            ],
            'scope' => ['flags' => FILTER_REQUIRE_SCALAR],
            'code' => ['flags' => FILTER_REQUIRE_SCALAR],
            'redirect_uri' => ['filter' => FILTER_SANITIZE_URL],
            'username' => ['flags' => FILTER_REQUIRE_SCALAR],
            'password' => ['flags' => FILTER_REQUIRE_SCALAR],
            'refresh_token' => ['flags' => FILTER_REQUIRE_SCALAR],
        ];

        if (null === $request) {
            $request = Request::createFromGlobals();
        }

        // Input data by default can be either POST or GET
        if ('POST' === $request->getMethod()) {
            $inputData = $request->request->all();
        } else {
            $inputData = $request->query->all();
        }

        // Basic authorization header
        $authHeaders = $this->getAuthorizationHeader($request);

        // Filter input data
        $input = filter_var_array($inputData, $filters);

        // Grant Type must be specified.
        if (!$input['grant_type']) {
            throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_INVALID_REQUEST, 'Invalid grant_type parameter or parameter missing');
        }

        // Authorize the client
        $clientCredentials = $this->getClientCredentials($inputData, $authHeaders);

        $client = $this->storage->getClient($clientCredentials[0]);

        if (!$client) {
            throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_INVALID_CLIENT, 'The client credentials are invalid');
        }

        if (false === $this->storage->checkClientCredentials($client, $clientCredentials[1])) {
            throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_INVALID_CLIENT, 'The client credentials are invalid');
        }

        if (!$this->storage->checkRestrictedGrantType($client, $input['grant_type'])) {
            throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_UNAUTHORIZED_CLIENT, 'The grant type is unauthorized for this client_id');
        }

        // Do the granting
        switch ($input['grant_type']) {
            case self::GRANT_TYPE_AUTH_CODE:
                // returns array('data' => data, 'scope' => scope)
                $stored = $this->grantAccessTokenAuthCode($client, $input);
                break;
            case self::GRANT_TYPE_USER_CREDENTIALS:
                // returns: true || array('scope' => scope)
                $stored = $this->grantAccessTokenUserCredentials($client, $input);
                break;
            case self::GRANT_TYPE_CLIENT_CREDENTIALS:
                // returns: true || array('scope' => scope)
                $stored = $this->grantAccessTokenClientCredentials($client, $input, $clientCredentials);
                break;
            case self::GRANT_TYPE_REFRESH_TOKEN:
                // returns array('data' => data, 'scope' => scope)
                $stored = $this->grantAccessTokenRefreshToken($client, $input);
                break;
            default:
                if (!str_starts_with($input['grant_type'], 'urn:')
                    && !filter_var($input['grant_type'], FILTER_VALIDATE_URL)
                ) {
                    throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_INVALID_REQUEST, 'Invalid grant_type parameter or parameter missing');
                }

                // returns: true || array('scope' => scope)
                $stored = $this->grantAccessTokenExtension($client, $inputData, $authHeaders);
        }

        if (!is_array($stored)) {
            $stored = [];
        }

        // if no scope provided to check against $input['scope'] then application defaults are set
        // if no data is provided than null is set
        $stored += ['scope' => $this->getVariable(self::CONFIG_SUPPORTED_SCOPES, null), 'data' => null,
            'access_token_lifetime' => $this->getVariable(self::CONFIG_ACCESS_LIFETIME),
            'issue_refresh_token' => true, 'refresh_token_lifetime' => $this->getVariable(self::CONFIG_REFRESH_LIFETIME), ];

        $scope = $input['scope']; // !!!!!!! THIS IS THE OVERRIDDEN PART !!!!!!!
        if ($input['scope']) {
            // Check scope, if provided
            if (!isset($stored['scope']) || !$this->checkScope($input['scope'], $stored['scope'])) {
                throw new OAuth2ServerException(Response::HTTP_BAD_REQUEST, self::ERROR_INVALID_SCOPE, 'An unsupported scope was requested.');
            }
            $scope = $input['scope'];
        }

        $token = $this->createAccessToken($client, $stored['data'], $scope, $stored['access_token_lifetime'], $stored['issue_refresh_token'], $stored['refresh_token_lifetime']);

        return new Response(json_encode($token, JSON_THROW_ON_ERROR), 200, $this->getJsonHeaders());
    }

    /**
     * Overriden because it was private
     * {@inheritdoc}
     */
    private function getJsonHeaders()
    {
        $headers = $this->getVariable(self::CONFIG_RESPONSE_EXTRA_HEADERS, []);
        $headers += [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ];

        return $headers;
    }

    public function createAccessToken(IOAuth2Client $client, $data, $scope = null, $access_token_lifetime = null, $issue_refresh_token = true, $refresh_token_lifetime = null)
    {
        if (!empty(trim((string) $scope))) {
            $scopes = explode(' ', $scope);
            $scopes = array_filter($scopes, fn (string $scope): bool => self::NO_SCOPE_PROVIDED !== $scope);

            if (!empty($scopes) && $client instanceof OAuthClient) {
                $this->validateClientAllowedScopes($client, $scopes);
            }

            $scope = implode(' ', $scopes);
        }

        return parent::createAccessToken($client, $data, $scope, $access_token_lifetime, $issue_refresh_token, $refresh_token_lifetime);
    }

    protected function checkScope($requiredScope, $availableScope)
    {
        if (empty($requiredScope)) {
            return true;
        }

        // The required scope should match or be a subset of the available scope
        if (!is_array($requiredScope)) {
            $requiredScope = explode(' ', trim((string) $requiredScope));
        }

        if (!is_array($availableScope)) {
            $availableScope = explode(' ', trim((string) $availableScope));
        }

        return 0 === count(array_diff($requiredScope, $availableScope));
    }

    private function validateClientAllowedScopes(OAuthClient $client, array $scopes): void
    {
        $allowedScopes = $client->getAllowedScopes();
        foreach ($scopes as $scope) {
            if (!in_array($scope, $allowedScopes)) {
                throw new OAuth2AuthenticateException(Response::HTTP_BAD_REQUEST, 'Bearer', 'Service', OAuth2::ERROR_INVALID_SCOPE, sprintf('Scope "%s" is not allowed for this client.', $scope));
            }
        }
    }
}
