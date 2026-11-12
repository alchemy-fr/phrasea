<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ExposeIntegrationControllerTest extends AbstractDataboxTestCase
{
    use ExposeTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string MEMBER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ACCESS_TOKEN_REFUSED = 'Expose refused the access token of the OAuth client "expose-app"';

    private FakeExposeApi $api;
    private WorkspaceIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new FakeExposeApi();
        $this->integration = $this->createExposeIntegration(null, self::OWNER);
        ExposeHttpClientMock::$api = $this->api;
    }

    protected function tearDown(): void
    {
        ExposeHttpClientMock::$api = null;
        parent::tearDown();
    }

    private function createPublication(string $userId, array $json): ResponseInterface
    {
        return static::createClient()->request('POST', '/integrations/expose/'.$this->integration->getId().'/publications', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
            'json' => $json,
        ]);
    }

    private function proxyPublications(string $userId): ResponseInterface
    {
        return static::createClient()->request('GET', '/integrations/expose/'.$this->integration->getId().'/proxy/publications', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
        ]);
    }

    /**
     * The message the client shows.
     */
    private static function getErrorMessage(ResponseInterface $response): string
    {
        $data = $response->toArray(false);

        return (string) ($data['detail'] ?? $data['description'] ?? $data['message'] ?? '');
    }

    public function testCreatesAPublicationFromTheAllowedProperties(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);

        $response = $this->createPublication(self::OWNER, [
            'title' => ' Press kit ',
            'slug' => 'press-kit',
            'description' => '',
            'enabled' => false,
            'parent' => 'parent-id',
            'profile' => '/publication-profiles/profile-id',
            'ownerId' => 'someone-else',
            'config' => ['securityMethod' => 'none'],
        ]);

        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame('Press kit', $data['title']);
        $this->assertSame([
            'title' => 'Press kit',
            'slug' => 'press-kit',
            'enabled' => false,
            'parent' => '/publications/parent-id',
            'profile' => '/publication-profiles/profile-id',
        ], $this->api->publications[$data['id']]['data']);
    }

    public function testRequiresATitle(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);

        $this->assertSame(400, $this->createPublication(self::OWNER, ['title' => ' '])->getStatusCode());
        $this->assertSame([], $this->api->publications);
    }

    public function testReportsTheRefusalOfExpose(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);
        $this->api->failures['POST /publications'] = 422;

        $response = $this->createPublication(self::OWNER, ['title' => 'Press kit']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('Failure', $response->getContent(false));
    }

    public function testExplainsTheRefusalOfTheAccessToken(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);
        $this->api->failures['POST /publications'] = 401;

        $response = $this->createPublication(self::OWNER, ['title' => 'Press kit']);

        $this->assertSame(400, $response->getStatusCode());
        $message = self::getErrorMessage($response);
        $this->assertStringContainsString(self::ACCESS_TOKEN_REFUSED, $message);
        $this->assertStringContainsString('redirect URIs under http://localhost/integrations/ only', $message);
    }

    public function testTheProxiesExplainTheRefusalOfTheAccessToken(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);
        $this->api->failures['GET /publications'] = 401;

        $response = $this->proxyPublications(self::OWNER);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString(self::ACCESS_TOKEN_REFUSED, self::getErrorMessage($response));
    }

    public function testTheProxiesReportTheRefusalOfExpose(): void
    {
        $this->createExposeToken($this->integration, self::OWNER);
        $this->api->failures['GET /publications'] = 403;

        $response = $this->proxyPublications(self::OWNER);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Failure', self::getErrorMessage($response));
    }

    public function testTheProxiesRequireAnAuthorizedIntegration(): void
    {
        $this->assertSame(403, $this->proxyPublications(self::OWNER)->getStatusCode());
        $this->assertSame([], $this->api->requests);
    }

    public function testRequiresTheRightToInteract(): void
    {
        $this->createExposeToken($this->integration, self::MEMBER);

        $this->assertSame(403, $this->createPublication(self::MEMBER, ['title' => 'Press kit'])->getStatusCode());

        $this->grantUserOnObject(self::MEMBER, $this->integration, PermissionInterface::CHILD_EDIT);
        $this->assertSame(201, $this->createPublication(self::MEMBER, ['title' => 'Press kit'])->getStatusCode());
    }

    public function testRequiresAnAuthorizedIntegration(): void
    {
        $this->assertSame(403, $this->createPublication(self::OWNER, ['title' => 'Press kit'])->getStatusCode());
        $this->assertSame([], $this->api->publications);
    }
}
