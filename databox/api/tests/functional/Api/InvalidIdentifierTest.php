<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;

/**
 * A malformed ID given in a query parameter or a body field is a bad request (400):
 * on Postgres, it would otherwise reach a `uuid` column and fail as an SQL error (500).
 * The test database (SQLite) has no such type: these tests check the explicit status.
 */
final class InvalidIdentifierTest extends AbstractDataboxTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string INVALID = 'not-a-uuid';

    /**
     * @return iterable<string, array{string, string, array<mixed>|null}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'attribute policies, workspaceId' => ['GET', '/attribute-policies?workspaceId='.self::INVALID, null];
        yield 'attribute filter rules, workspaceId' => ['GET', '/attribute-filter-rules?workspaceId='.self::INVALID, null];
        yield 'attributes, assetId' => ['GET', '/attributes?assetId='.self::INVALID, null];
        yield 'attributes, asset IRI' => ['GET', '/attributes?assetId=/assets/'.self::INVALID, null];
        yield 'attribute entities, workspace' => ['GET', '/attribute-entities?workspace='.self::INVALID, null];
        yield 'attribute entities, workspace list' => ['GET', '/attribute-entities?workspace[]='.self::INVALID, null];
        yield 'attribute entities, list' => ['GET', '/attribute-entities?list='.self::INVALID, null];
        yield 'rendition definitions, assetId' => ['GET', '/rendition-definitions?assetId='.self::INVALID, null];
        yield 'sort attribute definitions' => ['POST', '/attribute-definitions/sort', [self::INVALID]];
        yield 'delete assets by keys, workspaceId' => ['DELETE', '/assets-by-keys', ['keys' => ['k'], 'workspaceId' => self::INVALID]];
    }

    #[DataProvider('invalidIdProvider')]
    public function testMalformedIdIsABadRequest(string $method, string $uri, ?array $json): void
    {
        $this->createWorkspace(['ownerId' => self::USER]);

        $this->request($method, $uri, $json);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUnknownAssetOfRenditionDefinitionsIsNotFound(): void
    {
        $this->createWorkspace(['ownerId' => self::USER]);

        $this->request('GET', '/rendition-definitions?assetId='.Uuid::uuid4()->toString());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMalformedObjectIdOfIntegrationIsABadRequest(): void
    {
        $integration = $this->createOwnedIntegration();

        $this->request('GET', '/integrations/'.$integration->getId().'?objectType=asset&objectId='.self::INVALID);
        $this->assertResponseStatusCodeSame(400);

        // A repeated key is parsed as a list
        $this->request('GET', '/integrations/'.$integration->getId().'?objectType=asset&objectId=a&objectId=b');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUnknownObjectTypeOfIntegrationIsABadRequest(): void
    {
        $integration = $this->createOwnedIntegration();

        $this->request('GET', '/integrations/'.$integration->getId().'?objectType=unknown&objectId='.Uuid::uuid4()->toString());
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUnknownObjectOfIntegrationIsNotFound(): void
    {
        $integration = $this->createOwnedIntegration();

        $this->request('GET', '/integrations/'.$integration->getId().'?objectType=asset&objectId='.Uuid::uuid4()->toString());
        $this->assertResponseStatusCodeSame(404);
    }

    private function createOwnedIntegration(): WorkspaceIntegration
    {
        $workspace = $this->createWorkspace(['ownerId' => self::USER]);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setIntegration('core.webhook');
        $integration->setConfig(['url' => 'https://webhook.phrasea.test']);
        $integration->setOwnerId(self::USER);
        $integration->setPublic(false);
        $integration->setEnabled(true);
        $em = self::getEntityManager();
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function request(string $method, string $uri, ?array $json = null): void
    {
        self::getEntityManager()->clear();
        $options = [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER)],
        ];
        if (null !== $json) {
            $options['json'] = $json;
        }

        static::createClient()->request($method, $uri, $options);
    }
}
