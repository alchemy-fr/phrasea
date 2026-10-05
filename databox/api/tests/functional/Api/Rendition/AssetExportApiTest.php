<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Asset\AssetExportProcess;
use App\Entity\Core\AssetExport;
use App\Model\ExportStatusEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * POST /asset-exports, GET /asset-exports/{id}.
 */
final class AssetExportApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private function interceptP1(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p1');

        return $transport->intercept();
    }

    /**
     * @return array{0: string, 1: string} asset ID, rendition definition ID
     */
    private function createExportableAsset(): array
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::OWNER);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::OWNER]);
        $main = $defaults->renditionDefinitions['main'];
        $this->createAssetRendition($asset, $main, $this->createUrlFile($ws));

        return [$asset->getId(), $main->getId()];
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->jsonRequest('POST', '/asset-exports', null, ['assets' => ['x'], 'renditions' => ['y']]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateValidation(): void
    {
        $response = $this->jsonRequest('POST', '/asset-exports', self::OWNER, ['assets' => [], 'renditions' => []]);
        $this->assertResponseStatusCodeSame(422);
        $paths = array_column($response->toArray(false)['violations'], 'propertyPath');
        $this->assertEqualsCanonicalizing(['assets', 'renditions'], $paths);

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, []);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateIsDeniedOnAnUnreadableAsset(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();

        $this->jsonRequest('POST', '/asset-exports', self::OTHER, ['assets' => [$assetId], 'renditions' => [$definitionId]]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(0, self::getEntityManager()->getRepository(AssetExport::class)->count([]));
    }

    public function testCreateWithAnUnknownAssetIsNotFound(): void
    {
        $this->markTestIncomplete('BUG: AssetExportProcessor uses DoctrineUtil::findStrict() without $throw404 (src/Api/Processor/AssetExportProcessor.php:37): an unknown asset ID raises an \InvalidArgumentException, i.e. a 500.');

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => ['f1b4b4a8-0000-4000-8000-000000000000'],
            'renditions' => ['f1b4b4a8-0000-4000-8000-000000000001'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateQueuesTheExport(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $transport = $this->interceptP1();

        $response = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'asset-export',
            'status' => ExportStatusEnum::Pending->value,
            'owner' => [
                'id' => self::OWNER,
                'username' => 'user',
            ],
        ]);
        $data = $response->toArray();
        $this->assertArrayNotHasKey('downloadUrl', $data, 'No archive yet');
        $this->assertArrayNotHasKey('assets', $data, 'Write-only');
        $this->assertArrayNotHasKey('renditions', $data, 'Write-only');

        $messages = array_values(array_filter(
            $transport->getSent(),
            fn ($envelope): bool => $envelope->getMessage() instanceof AssetExportProcess,
        ));
        $this->assertCount(1, $messages);
        $this->assertSame($data['id'], $messages[0]->getMessage()->id);

        /** @var AssetExport $export */
        $export = self::getEntityManager()->find(AssetExport::class, $data['id']);
        $this->assertSame([$assetId], $export->getAssets());
        $this->assertSame([$definitionId], $export->getRenditions());
        $this->assertSame(self::OWNER, $export->getOwnerId());
    }

    public function testGetExport(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OWNER);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/asset-exports/'.$exportId,
            'id' => $exportId,
            'status' => ExportStatusEnum::Pending->value,
            'owner' => ['id' => self::OWNER],
        ]);

        $this->jsonRequest('GET', '/asset-exports/f1b4b4a8-0000-4000-8000-000000000000', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testReadyExportExposesASignedDownloadUrl(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $em = self::getEntityManager();
        /** @var AssetExport $export */
        $export = $em->find(AssetExport::class, $exportId);
        $export->setStatus(ExportStatusEnum::Ready);
        $export->setPath('exports/archive.zip');
        $em->flush();

        $data = $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OWNER)->toArray();
        $this->assertSame(ExportStatusEnum::Ready->value, $data['status']);
        $this->assertStringContainsString('exports/archive.zip', $data['downloadUrl']);
    }

    public function testExportOfAnotherUserIsNotReadable(): void
    {
        $this->markTestIncomplete('BUG: GET /asset-exports/{id} has no object-level security (src/Entity/Core/AssetExport.php:27): any authenticated user can read the export of another user, including its signed downloadUrl.');

        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }
}
