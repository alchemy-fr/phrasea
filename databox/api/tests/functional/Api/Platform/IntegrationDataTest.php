<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * What integrations store (data, OAuth tokens) and the user actions they
 * expose (/integrations/{id}/actions/{action}).
 */
final class IntegrationDataTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    private function listData(string $userId, string $integrationId, array $query = []): array
    {
        $response = static::createClient()->request('GET', '/integrations/'.$integrationId.'/data', [
            'headers' => $this->headers($userId),
            'query' => $query,
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray()['hydra:member'];
    }

    private function findData(string $id): ?IntegrationData
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(IntegrationData::class, $id);
    }

    public function testEditorsSeeAllTheDataAndOthersTheirOwn(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $ownerData = $this->createIntegrationData($integration, self::OWNER, value: 'owner value');
        $memberData = $this->createIntegrationData($integration, self::MEMBER, value: 'member value');
        $id = $integration->getId();

        $items = $this->listData(self::OWNER, $id);
        $this->assertEqualsCanonicalizing([$ownerData->getId(), $memberData->getId()], array_column($items, 'id'));

        $items = $this->listData(self::MEMBER, $id);
        $this->assertSame([$memberData->getId()], array_column($items, 'id'));
        $this->assertSame('note', $items[0]['name']);
        $this->assertSame('member value', $items[0]['value']);

        // Asking for the data of another user is ignored: the filter is
        // forced to the current user
        $this->assertSame([$memberData->getId()], array_column($this->listData(self::MEMBER, $id, ['userId' => self::OWNER]), 'id'));
    }

    public function testDataIsFilteredByObject(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true);
        $basket = $this->createBasket(['ownerId' => self::OWNER]);
        $otherBasket = $this->createBasket(['ownerId' => self::OWNER]);
        $onBasket = $this->createIntegrationData($integration, self::OWNER, $basket, 'publication');
        $this->createIntegrationData($integration, self::OWNER, $otherBasket, 'publication');
        $this->createIntegrationData($integration, self::OWNER, null, 'publication');

        $items = $this->listData(self::OWNER, $integration->getId(), [
            'objectType' => 'basket',
            'objectId' => $basket->getId(),
        ]);

        $this->assertSame([$onBasket->getId()], array_column($items, 'id'));
        $this->assertCount(3, $this->listData(self::OWNER, $integration->getId()));
    }

    public function testDataOfAnInaccessibleWorkspaceCannotBeListed(): void
    {
        $integration = $this->createIntegration($this->createOtherWorkspace(), 'remove.bg', public: true, ownerId: 'someone-else');

        static::createClient()->request('GET', '/integrations/'.$integration->getId().'/data', [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDataOfAnUnknownIntegrationIs404(): void
    {
        static::createClient()->request('GET', '/integrations/00000000-0000-4000-8000-000000000000/data', [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUsersListTheirDataOfAnInstanceWideIntegration(): void
    {
        $this->markTestIncomplete('BUG: IntegrationDataProvider checks READ on $integration->getWorkspace() without handling instance-wide integrations (null workspace, e.g. Expose used from baskets): only admins pass, users get a 403. IntegrationTokenDataProvider does skip the check when there is no workspace (src/Api/Provider/IntegrationDataProvider.php:31).');

        $global = $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: KeycloakClientTestMock::ADMIN_UID);
        $basket = $this->createBasket(['ownerId' => self::MEMBER]);
        $data = $this->createIntegrationData($global, self::MEMBER, $basket, 'publication');

        $items = $this->listData(self::MEMBER, $global->getId(), [
            'objectType' => 'basket',
            'objectId' => $basket->getId(),
        ]);

        $this->assertSame([$data->getId()], array_column($items, 'id'));
    }

    public function testTokensOfAnIntegration(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true);
        $valid = $this->createIntegrationToken($integration, self::MEMBER);
        $expired = $this->createIntegrationToken($integration, self::MEMBER, expiresAt: '-1 day');

        $response = static::createClient()->request('GET', '/integrations/'.$integration->getId().'/tokens', [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseIsSuccessful();
        $items = array_column($response->toArray()['hydra:member'], null, 'id');
        $this->assertEqualsCanonicalizing([$valid->getId(), $expired->getId()], array_keys($items));
        $this->assertFalse($items[$valid->getId()]['expired']);
        $this->assertTrue($items[$expired->getId()]['expired']);
        $this->assertSame(self::MEMBER, $items[$valid->getId()]['userId']);
        $this->assertStringNotContainsString(self::SECRET_ACCESS_TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::SECRET_REFRESH_TOKEN, $response->getContent());
    }

    public function testUsersOnlyListTheirOwnTokens(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true);
        $mine = $this->createIntegrationToken($integration, self::MEMBER);
        $this->createIntegrationToken($integration, self::OWNER);

        $response = static::createClient()->request('GET', '/integrations/'.$integration->getId().'/tokens', [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([$mine->getId()], array_column($response->toArray()['hydra:member'], 'id'));
    }

    public function testTokensOfAnInaccessibleWorkspaceCannotBeListed(): void
    {
        $integration = $this->createIntegration($this->createOtherWorkspace(), 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: 'someone-else');

        static::createClient()->request('GET', '/integrations/'.$integration->getId().'/tokens', [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);

        static::createClient()->request('GET', '/integrations/00000000-0000-4000-8000-000000000000/tokens', [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * No voter supports IntegrationData nor IntegrationToken: their item
     * routes are only reachable by admins (AdminVoter), even for the user
     * who owns the data or the token.
     */
    public function testDataItemRoutesAreReservedToAdmins(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $data = $this->createIntegrationData($integration, self::OWNER, value: 'v1');
        $id = $data->getId();
        $integrationIri = '/integrations/'.$integration->getId();
        $client = static::createClient();

        $client->request('GET', '/integration-datas/'.$id, ['headers' => $this->headers(self::OWNER)]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('PUT', '/integration-datas/'.$id, [
            'headers' => $this->headers(self::OWNER),
            'json' => ['value' => 'hacked'],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('DELETE', '/integration-datas/'.$id, ['headers' => $this->headers(self::OWNER)]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/integration-datas', [
            'headers' => $this->headers(self::OWNER),
            'json' => ['integration' => $integrationIri, 'name' => 'n', 'value' => 'v'],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('v1', $this->findData($id)->getValue());

        $admin = $this->headers(KeycloakClientTestMock::ADMIN_UID);
        $response = $client->request('GET', '/integration-datas/'.$id, ['headers' => $admin]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(['name' => 'note', 'value' => 'v1', 'id' => $id], array_intersect_key($response->toArray(), ['name' => 1, 'value' => 1, 'id' => 1]));

        $client->request('PUT', '/integration-datas/'.$id, [
            'headers' => $admin,
            'json' => ['value' => 'v2'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('v2', $this->findData($id)->getValue());

        $response = $client->request('POST', '/integration-datas', [
            'headers' => $admin,
            'json' => ['integration' => $integrationIri, 'name' => 'created', 'value' => 'by admin'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $created = $this->findData($response->toArray()['id']);
        $this->assertSame($integration->getId(), $created->getIntegration()->getId());
        $this->assertSame('by admin', $created->getValue());

        $client->request('DELETE', '/integration-datas/'.$id, ['headers' => $admin]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findData($id));
    }

    public function testCreatingDataValidatesIt(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);

        $response = static::createClient()->request('POST', '/integration-datas', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => ['integration' => '/integrations/'.$integration->getId(), 'name' => ''],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertEqualsCanonicalizing(['name', 'value'], array_column($response->toArray(false)['violations'], 'propertyPath'));
    }

    public function testTokenItemRoutesAreReservedToAdmins(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true);
        $token = $this->createIntegrationToken($integration, self::MEMBER);
        $id = $token->getId();
        $client = static::createClient();

        $client->request('GET', '/integration-tokens/'.$id, ['headers' => $this->headers(self::MEMBER)]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('DELETE', '/integration-tokens/'.$id, ['headers' => $this->headers(self::MEMBER)]);
        $this->assertResponseStatusCodeSame(403);

        $admin = $this->headers(KeycloakClientTestMock::ADMIN_UID);
        $response = $client->request('GET', '/integration-tokens/'.$id, ['headers' => $admin]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(self::MEMBER, $data['userId']);
        $this->assertFalse($data['expired']);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertStringNotContainsString(self::SECRET_ACCESS_TOKEN, $response->getContent());

        $client->request('DELETE', '/integration-tokens/'.$id, ['headers' => $admin]);
        $this->assertResponseStatusCodeSame(204);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(IntegrationToken::class, $id));
    }

    /**
     * A Toast UI photo editor in the shared workspace, an asset of MEMBER
     * with its source file, and MEMBER allowed to interact (CHILD_EDIT).
     *
     * @return array{string, string} [integration ID, file ID]
     */
    private function createPhotoEditorSetup(bool $canInteract = true): array
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'tui.photo-editor', public: true);
        if ($canInteract) {
            $this->grantUserOnObject(self::MEMBER, $integration, PermissionInterface::CHILD_EDIT);
        }
        $file = $this->createFile($workspace);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => self::MEMBER,
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        self::getEntityManager()->flush();

        return [$integration->getId(), $file->getId()];
    }

    private function createFile(Workspace $workspace): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setType('image/jpeg');
        self::getEntityManager()->persist($file);

        return $file;
    }

    public function testInteractingRequiresTheChildEditPermission(): void
    {
        [$integrationId, $fileId] = $this->createPhotoEditorSetup(canInteract: false);

        $response = static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'headers' => $this->headers(self::MEMBER),
            'json' => ['fileId' => $fileId, 'id' => 'x'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Cannot interact with this integration', $response->toArray(false)['hydra:description']);
    }

    public function testAnActionRequiresEditingTheFile(): void
    {
        [$integrationId] = $this->createPhotoEditorSetup();
        $workspace = self::getEntityManager()->getRepository(Workspace::class)->findOneBy(['slug' => 'my-workspace']);
        // A file of an asset the member cannot edit
        $file = $this->createFile($workspace);
        $asset = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setSource($file);
        self::getEntityManager()->flush();

        static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'headers' => $this->headers(self::MEMBER),
            'json' => ['fileId' => $file->getId(), 'id' => 'x'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeletingDataThroughAnAction(): void
    {
        [$integrationId, $fileId] = $this->createPhotoEditorSetup();
        $em = self::getEntityManager();
        $integration = $em->find(WorkspaceIntegration::class, $integrationId);
        $file = $em->find(File::class, $fileId);
        $mine = $this->createIntegrationData($integration, self::MEMBER, $file, 'file_id', 'new-file-id');
        $notMine = $this->createIntegrationData($integration, self::OWNER, $file, 'file_id', 'other-file-id');
        $em->clear();

        $response = static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'headers' => $this->headers(self::MEMBER),
            'json' => ['fileId' => $fileId, 'id' => $mine->getId()],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $response->toArray());
        $this->assertNull($this->findData($mine->getId()));

        // The data of the others is out of reach
        static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'headers' => $this->headers(self::MEMBER),
            'json' => ['fileId' => $fileId, 'id' => $notMine->getId()],
        ]);
        $this->assertGreaterThanOrEqual(400, static::getClient()->getResponse()->getStatusCode());
        $this->assertNotNull($this->findData($notMine->getId()));
    }

    public function testAnActionValidatesItsInput(): void
    {
        [$integrationId, $fileId] = $this->createPhotoEditorSetup();

        $response = static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'headers' => $this->headers(self::MEMBER),
            'json' => ['fileId' => $fileId],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Missing "id"', $response->toArray(false)['hydra:description']);
    }

    public static function getInvalidActionCalls(): iterable
    {
        yield 'unknown integration' => ['unknown-integration', 'delete', 404];
        yield 'unknown action' => ['photo-editor', 'explode', 400];
        yield 'missing file' => ['photo-editor', 'delete-without-file', 400];
        yield 'integration without user actions' => ['webhook', 'delete', 400];
    }

    /**
     * @dataProvider getInvalidActionCalls
     */
    public function testInvalidActionCallsAreRejectedCleanly(string $target, string $action, int $expectedCode): void
    {
        $this->markTestIncomplete('BUG: the integration action endpoint turns client errors into 500: unknown integration (IntegrationManager::loadIntegration throws \InvalidArgumentException, src/Integration/IntegrationManager.php:62), integration without user actions (src/Integration/IntegrationManager.php:49), unknown action (\InvalidArgumentException in TuiPhotoEditorIntegration::handleUserAction, src/Integration/ToastUi/TuiPhotoEditorIntegration.php:65, same in RemoveBgIntegration) and missing "fileId" (TypeError in FileUserActionsTrait::getFile, src/Integration/Action/FileUserActionsTrait.php:36).');

        [$integrationId, $fileId] = $this->createPhotoEditorSetup();
        $payload = ['fileId' => $fileId, 'id' => 'x'];
        if ('unknown-integration' === $target) {
            $integrationId = '00000000-0000-4000-8000-000000000000';
        } elseif ('webhook' === $target) {
            $workspace = self::getEntityManager()->getRepository(Workspace::class)->findOneBy(['slug' => 'my-workspace']);
            $webhook = $this->createIntegration($workspace, 'core.webhook', ['url' => 'https://hook.phrasea.test'], public: true);
            $this->grantUserOnObject(self::MEMBER, $webhook, PermissionInterface::CHILD_EDIT);
            $integrationId = $webhook->getId();
        }
        if ('delete-without-file' === $action) {
            $action = 'delete';
            unset($payload['fileId']);
        }

        static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/'.$action, [
            'headers' => $this->headers(self::MEMBER),
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
    }

    public function testAnonymousCannotRunActions(): void
    {
        [$integrationId, $fileId] = $this->createPhotoEditorSetup();

        static::createClient()->request('POST', '/integrations/'.$integrationId.'/actions/delete', [
            'json' => ['fileId' => $fileId, 'id' => 'x'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
