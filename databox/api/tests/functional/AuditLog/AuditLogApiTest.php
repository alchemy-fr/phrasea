<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Collection;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * GET /audit-logs, GET /audit-logs/{id}: admins only.
 */
class AuditLogApiTest extends AbstractDataboxTestCase
{
    use AuditLogTestTrait;

    private const string WORKSPACE_ID = 'a0000000-0000-4000-8000-000000000001';
    private const string OTHER_WORKSPACE_ID = 'a0000000-0000-4000-8000-000000000002';
    private const string CORRELATION_ID = 'c0000000-0000-4000-8000-000000000001';

    public function testNonAdminIsDenied(): void
    {
        $this->request('/audit-logs', KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(403);

        $this->request('/audit-logs', null);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAdminListsTheLastEntriesFirst(): void
    {
        $ids = $this->createLogs();

        $response = $this->request('/audit-logs', KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame(3, $data['totalItems']);
        $this->assertSame([$ids['restored'], $ids['moved'], $ids['created']], array_column($data['member'], 'id'));

        $moved = $data['member'][1];
        $this->assertSame('asset_moved', $moved['action']);
        $this->assertSame('user', $moved['actorType']);
        $this->assertSame(['id' => KeycloakClientTestMock::USER_UID, 'username' => 'alice'], $moved['user']);
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $moved['impersonatorId']);
        $this->assertSame('asset', $moved['objectType']);
        $this->assertSame('Sunset', $moved['objectLabel']);
        $this->assertSame(self::WORKSPACE_ID, $moved['workspaceId']);
        $this->assertSame(self::CORRELATION_ID, $moved['correlationId']);
        $this->assertSame(['oldCollectionId' => null, 'newCollectionId' => 'x'], $moved['data']);

        $this->request('/audit-logs/'.$ids['moved'], KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['id' => $ids['moved'], 'action' => 'asset_moved']);
    }

    public function testFilters(): void
    {
        $ids = $this->createLogs();
        $admin = KeycloakClientTestMock::ADMIN_UID;

        $this->assertSame([$ids['moved']], $this->listIds('/audit-logs?action[]=asset_moved', $admin));
        $this->assertSame([$ids['restored'], $ids['moved']], $this->listIds('/audit-logs?action[]=asset_moved&action[]=object_restored', $admin));
        $this->assertSame([], $this->listIds('/audit-logs?action[]=unknown', $admin));
        $this->assertSame([$ids['moved'], $ids['created']], $this->listIds('/audit-logs?objectId=asset-1', $admin));
        $this->assertSame([$ids['created']], $this->listIds('/audit-logs?objectType=collection', $admin));
        $this->assertSame([$ids['restored']], $this->listIds('/audit-logs?workspaceId='.self::OTHER_WORKSPACE_ID, $admin));
        $this->assertSame([$ids['moved']], $this->listIds('/audit-logs?correlationId='.self::CORRELATION_ID, $admin));
        // The acting user, or the admin acting as them
        $this->assertSame([$ids['moved']], $this->listIds('/audit-logs?userId='.KeycloakClientTestMock::ADMIN_UID, $admin));
        $this->assertSame([$ids['restored']], $this->listIds('/audit-logs?actorType=system', $admin));
        $this->assertSame([$ids['restored'], $ids['moved']], $this->listIds('/audit-logs?'.http_build_query(['date' => ['after' => '2026-01-02T00:00:00+00:00']]), $admin));
        $this->assertSame([$ids['created']], $this->listIds('/audit-logs?'.http_build_query(['date' => ['before' => '2026-01-01T12:00:00+00:00']]), $admin));
    }

    /**
     * @return array<string, string>
     */
    private function createLogs(): array
    {
        $em = self::getEntityManager();

        $created = new AuditLog(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $created->setAction(AuditLogActionEnum::ObjectCreated);
        $created->setActorType(AuditActorTypeEnum::User);
        $created->setUserId(KeycloakClientTestMock::USER_UID);
        $created->setUserName('alice');
        $created->setObjectType(Collection::OBJECT_INDEX);
        $created->setObjectId('asset-1');
        $created->setWorkspaceId(self::WORKSPACE_ID);
        $em->persist($created);

        $moved = new AuditLog(new \DateTimeImmutable('2026-01-02T10:00:00+00:00'));
        $moved->setAction(AuditLogActionEnum::AssetMoved);
        $moved->setActorType(AuditActorTypeEnum::User);
        $moved->setUserId(KeycloakClientTestMock::USER_UID);
        $moved->setUserName('alice');
        $moved->setImpersonatorId(KeycloakClientTestMock::ADMIN_UID);
        $moved->setObjectType(\App\Entity\Core\Asset::OBJECT_INDEX);
        $moved->setObjectId('asset-1');
        $moved->setObjectLabel('Sunset');
        $moved->setWorkspaceId(self::WORKSPACE_ID);
        $moved->setCorrelationId(self::CORRELATION_ID);
        $moved->setData(['oldCollectionId' => null, 'newCollectionId' => 'x']);
        $em->persist($moved);

        $restored = new AuditLog(new \DateTimeImmutable('2026-01-03T10:00:00+00:00'));
        $restored->setAction(AuditLogActionEnum::ObjectRestored);
        $restored->setActorType(AuditActorTypeEnum::System);
        $restored->setWorkspaceId(self::OTHER_WORKSPACE_ID);
        $em->persist($restored);

        $em->flush();

        return [
            'created' => $created->getId(),
            'moved' => $moved->getId(),
            'restored' => $restored->getId(),
        ];
    }

    /**
     * @return string[]
     */
    private function listIds(string $uri, string $userId): array
    {
        $response = $this->request($uri, $userId);
        $this->assertResponseStatusCodeSame(200, $uri);

        return array_column($response->toArray()['member'], 'id');
    }

    private function request(string $uri, ?string $userId): ResponseInterface
    {
        $options = [];
        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }

        return static::createClient()->request('GET', $uri, $options);
    }
}
