<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Security\DataboxIntegrationClient;
use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Api\Provider\OAuthClientProvider;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The Keycloak OAuth clients of the integrations (/oauth-clients), read and created with the
 * token of the user.
 */
final class OAuthClientTest extends AbstractDataboxTestCase
{
    private const string CLIENTS_PATH = '/admin/realms/phrasea/clients';
    private const string INTEGRATION_ID = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();
        KeycloakClientTestMock::$createdClients = [];
        unset(KeycloakClientTestMock::$lastQueries[self::CLIENTS_PATH]);
    }

    protected function tearDown(): void
    {
        KeycloakClientTestMock::$createdClients = [];
        parent::tearDown();
    }

    private function list(string $jwt, array $query = [], array $headers = []): ResponseInterface
    {
        return static::createClient()->request('GET', '/oauth-clients', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$jwt,
                ...$headers,
            ],
            'query' => $query,
        ]);
    }

    private function create(string $jwt, array $json, array $headers = []): ResponseInterface
    {
        return static::createClient()->request('POST', '/oauth-clients', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$jwt,
                ...$headers,
            ],
            'json' => $json,
        ]);
    }

    private static function adminJwt(): string
    {
        return KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
    }

    /**
     * @return array<string, bool> client ID => redirectUriAllowed, in the order of the response
     */
    private static function allowedByClientId(ResponseInterface $response): array
    {
        $allowed = [];
        foreach ($response->toArray() as $client) {
            $allowed[$client['clientId']] = $client['redirectUriAllowed'];
        }

        return $allowed;
    }

    public function testUserWithoutKeycloakRoleIsDenied(): void
    {
        $jwt = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);

        $this->assertSame(403, $this->list($jwt)->getStatusCode());
        $this->assertSame(403, $this->create($jwt, ['clientId' => 'my-client'])->getStatusCode());
        $this->assertSame([], KeycloakClientTestMock::$createdClients);
    }

    public function testAdminWithoutKeycloakRoleIsDenied(): void
    {
        // Admin of the application, but not of the Keycloak realm
        $jwt = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID, resourceAccess: []);

        $this->assertSame(403, $this->list($jwt)->getStatusCode());
        $this->assertSame(403, $this->create($jwt, ['clientId' => 'my-client'])->getStatusCode());
        $this->assertArrayNotHasKey(self::CLIENTS_PATH, KeycloakClientTestMock::$lastQueries);
        $this->assertSame([], KeycloakClientTestMock::$createdClients);
    }

    public function testListsTheCompatibleClientsAllowingTheRedirectUriFirst(): void
    {
        $response = $this->list(self::adminJwt());

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        // Neither the confidential client nor the built-in one
        $this->assertSame([
            'expose-integration' => true,
            'an-integration' => false,
            'databox-app' => false,
        ], self::allowedByClientId($response));
        $this->assertSame(500, KeycloakClientTestMock::$lastQueries[self::CLIENTS_PATH]['max']);

        $expose = $response->toArray()[0];
        $this->assertSame('c0000001-fc1a-492b-9e76-c2e8e6979786', $expose['id']);
        $this->assertSame('Expose integration', $expose['name']);
        $this->assertSame(['http://localhost/*'], $expose['redirectUris']);
    }

    public function testTellsTheClientsExposeAccepts(): void
    {
        $response = $this->list(self::adminJwt());

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $accepted = [];
        foreach ($response->toArray() as $client) {
            $accepted[$client['clientId']] = $client['acceptedByExpose'];
        }
        // Created by Databox; neither the client of Databox itself nor one allowing any URI of the API
        $this->assertSame([
            'expose-integration' => false,
            'an-integration' => true,
            'databox-app' => false,
        ], $accepted);
    }

    public function testExposeAcceptsTheClientsOfItsAllowedList(): void
    {
        $provider = new OAuthClientProvider(
            self::getService(KeycloakClient::class),
            self::getService(OAuthRedirectUriMatcher::class),
            [' expose-integration '],
        );

        $this->assertTrue($provider->createClient(KeycloakClientTestMock::CLIENTS[0], 'http://localhost/integrations/x/code')->acceptedByExpose);
        $this->assertFalse($provider->createClient(KeycloakClientTestMock::CLIENTS[1], 'http://localhost/integrations/x/code')->acceptedByExpose);
    }

    public function testListsTheClientsExposeAcceptsFirst(): void
    {
        $created = $this->create(self::adminJwt(), ['clientId' => 'z-integration']);
        $this->assertSame(201, $created->getStatusCode(), $created->getContent(false));

        $response = $this->list(self::adminJwt(), ['integrationId' => self::INTEGRATION_ID]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        // Among the clients allowing the redirect URI of the integration, the ones Expose accepts come first
        $this->assertSame([
            'an-integration' => true,
            'z-integration' => true,
            'expose-integration' => true,
            'databox-app' => false,
        ], self::allowedByClientId($response));
    }

    public function testListsAsJsonLd(): void
    {
        $response = $this->list(self::adminJwt(), headers: ['Accept' => 'application/ld+json']);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame(3, $data['totalItems']);
        $this->assertSame('expose-integration', $data['member'][0]['clientId']);
        $this->assertArrayHasKey('@id', $data['member'][0]);

        $response = $this->create(self::adminJwt(), ['clientId' => 'databox-expose-test'], ['Accept' => 'application/ld+json']);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('databox-expose-test', $response->toArray()['clientId']);
    }

    public function testChecksTheRedirectUriOfTheGivenIntegration(): void
    {
        $response = $this->list(self::adminJwt(), ['integrationId' => self::INTEGRATION_ID]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([
            'an-integration' => true,
            'expose-integration' => true,
            'databox-app' => false,
        ], self::allowedByClientId($response));

        $this->assertSame(400, $this->list(self::adminJwt(), ['integrationId' => 'a/b'])->getStatusCode());
    }

    public function testSearchesOnTheClientId(): void
    {
        $response = $this->list(self::adminJwt(), ['search' => 'EXPOSE']);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['expose-integration' => true], self::allowedByClientId($response));
        $query = KeycloakClientTestMock::$lastQueries[self::CLIENTS_PATH];
        $this->assertSame('EXPOSE', $query['clientId']);
        $this->assertSame('true', $query['search']);
    }

    public function testCreatesAPublicClientAllowingEveryIntegration(): void
    {
        $response = $this->create(self::adminJwt(), [
            'clientId' => 'databox-expose-test',
            'name' => 'Expose test',
            'description' => 'For the tests',
        ]);

        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame('databox-expose-test', $data['clientId']);
        $this->assertSame('Expose test', $data['name']);
        $this->assertTrue($data['redirectUriAllowed']);
        $this->assertTrue($data['acceptedByExpose']);

        $sent = KeycloakClientTestMock::$createdClients['databox-expose-test'];
        $this->assertSame('Expose test', $sent['name']);
        $this->assertSame('For the tests', $sent['description']);
        $this->assertTrue($sent['enabled']);
        $this->assertTrue($sent['publicClient']);
        $this->assertFalse($sent['bearerOnly']);
        $this->assertTrue($sent['standardFlowEnabled']);
        $this->assertFalse($sent['implicitFlowEnabled']);
        $this->assertFalse($sent['directAccessGrantsEnabled']);
        $this->assertFalse($sent['serviceAccountsEnabled']);
        $this->assertSame('http://localhost', $sent['rootUrl']);
        $this->assertSame(['http://localhost/integrations/*'], $sent['redirectUris']);
        $this->assertSame([DataboxIntegrationClient::CREATED_BY_ATTRIBUTE => DataboxIntegrationClient::CREATED_BY], $sent['attributes']);

        // Listed along the others, as allowing any integration
        $this->assertTrue(self::allowedByClientId($this->list(self::adminJwt(), ['integrationId' => self::INTEGRATION_ID]))['databox-expose-test']);
    }

    public function testNamesTheClientAfterItsIdByDefault(): void
    {
        $response = $this->create(self::adminJwt(), ['clientId' => 'databox-expose-test']);

        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('databox-expose-test', KeycloakClientTestMock::$createdClients['databox-expose-test']['name']);
    }

    public function testRefusesATakenClientId(): void
    {
        $response = $this->create(self::adminJwt(), ['clientId' => KeycloakClientTestMock::EXISTING_CLIENT_ID]);

        $this->assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $violations = $response->toArray(false)['violations'];
        $this->assertSame('clientId', $violations[0]['propertyPath']);
        $this->assertSame('A client with this ID already exists.', $violations[0]['message']);
    }

    public function testRefusesAnInvalidClientId(): void
    {
        foreach (['', 'my client', 'client/1', str_repeat('a', 256)] as $clientId) {
            $response = $this->create(self::adminJwt(), ['clientId' => $clientId]);

            $this->assertSame(422, $response->getStatusCode(), $clientId);
            $this->assertSame('clientId', $response->toArray(false)['violations'][0]['propertyPath']);
        }
        $this->assertSame([], KeycloakClientTestMock::$createdClients);
    }

    public function testImpersonatingAdminIsDenied(): void
    {
        $headers = [Impersonator::HEADER => KeycloakClientTestMock::USER_UID];

        $this->assertSame(403, $this->list(self::adminJwt(), headers: $headers)->getStatusCode());
        $this->assertSame(403, $this->create(self::adminJwt(), ['clientId' => 'my-client'], $headers)->getStatusCode());
        $this->assertSame([], KeycloakClientTestMock::$createdClients);
    }

    public function testTheExposeClientIdIsAnOAuthClient(): void
    {
        $response = static::createClient()->request('GET', '/integration-types/phrasea--expose', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.self::adminJwt(),
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('oauth_client', $response->toArray()['schema']['properties']['clientId']['widget']);
    }
}
