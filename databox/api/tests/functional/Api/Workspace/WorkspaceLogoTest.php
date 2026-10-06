<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST/DELETE /workspaces/{id}/logo, complementing WorkspaceTermsTest::testWorkspaceLogoUpload
 * (happy path): permissions, size limit, replacement cleanup and exposure.
 */
final class WorkspaceLogoTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Simulates a finished S3 multipart upload (see WorkspaceTermsTest).
     */
    private function createCompletedUpload(string $content, string $name, string $type, ?int $size = null): array
    {
        $em = self::getEntityManager();

        $upload = new MultipartUpload();
        $upload->setFilename($name);
        $upload->setType($type);
        $upload->setSize($size ?? strlen($content));
        $upload->setPath(sprintf('test-uploads/%s.%s', uniqid(), pathinfo($name, PATHINFO_EXTENSION)));
        $upload->setUploadId('test-'.uniqid());
        $upload->setComplete(true);
        $em->persist($upload);
        $em->flush();

        self::getService(FileStorageManager::class)->store($upload->getPath(), $content);

        return [
            'uploadId' => $upload->getId(),
            'parts' => [
                ['ETag' => 'test-etag', 'PartNumber' => 1],
            ],
        ];
    }

    private function uploadLogo(Workspace|string $workspace, ?string $userId, ?array $multipart = null): void
    {
        static::createClient()->request('POST', self::iri($workspace).'/logo', [
            'headers' => self::authHeaders($userId),
            'json' => [
                'multipart' => $multipart ?? $this->createCompletedUpload(base64_decode(self::PNG_BASE64), 'logo.png', 'image/png'),
            ],
        ]);
    }

    public function testLogoReplacementDeletesThePreviousFile(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        $this->uploadLogo($ws, self::USER);
        $this->assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $em->clear();
        $firstLogo = $em->find(Workspace::class, $ws->getId())->getLogoFile();
        $this->assertInstanceOf(File::class, $firstLogo);
        $this->assertSame('image/png', $firstLogo->getType());
        $this->assertSame($ws->getId(), $firstLogo->getWorkspace()->getId());
        $firstLogoId = $firstLogo->getId();
        $firstLogoPath = $firstLogo->getPath();

        $storage = self::getService(FileStorageManager::class);
        $this->assertTrue($storage->has($firstLogoPath));

        // Upload another logo (SVG is accepted too)
        $this->uploadLogo($ws, self::USER, $this->createCompletedUpload('<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg', 'image/svg+xml'));
        $this->assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $em->clear();
        $secondLogo = $em->find(Workspace::class, $ws->getId())->getLogoFile();
        $this->assertNotSame($firstLogoId, $secondLogo->getId());
        $this->assertSame('image/svg+xml', $secondLogo->getType());
        // The previous logo is removed from the database and the storage
        $this->assertNull($em->find(File::class, $firstLogoId));
        $this->assertFalse(self::getService(FileStorageManager::class)->has($firstLogoPath));

        // Removing the logo deletes its file as well
        static::createClient()->request('DELETE', self::iri($ws).'/logo', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseStatusCodeSame(204);
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Workspace::class, $ws->getId())->getLogoFile());
        $this->assertNull($em->find(File::class, $secondLogo->getId()));
    }

    public function testDeletingAMissingLogoIsANoop(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        static::createClient()->request('DELETE', self::iri($ws).'/logo', [
            'headers' => self::authHeaders(self::USER),
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testLogoIsExposedInItemAndListEvenToAnonymous(): void
    {
        $ws = $this->createWs('ws', ['public' => true, 'ownerId' => self::USER]);
        $this->uploadLogo($ws, self::USER);
        $this->assertResponseIsSuccessful();

        $client = static::createClient();
        $response = $client->request('GET', self::iri($ws));
        $this->assertResponseIsSuccessful();
        $this->assertNotEmpty($response->toArray()['logo']);

        $response = $client->request('GET', '/workspaces');
        $this->assertNotEmpty($response->toArray()['member'][0]['logo']);
    }

    public static function accessProvider(): array
    {
        return [
            // user, ACE mask (null = none), expected status
            'anonymous' => [null, null, 401],
            'user without access' => [self::USER, null, 403],
            'user with VIEW' => [self::USER, PermissionInterface::VIEW, 403],
            'user with EDIT' => [self::USER, PermissionInterface::VIEW | PermissionInterface::EDIT, 201],
            'admin' => [self::ADMIN, null, 201],
        ];
    }

    #[DataProvider('accessProvider')]
    public function testUploadAccess(?string $userId, ?int $mask, int $expectedStatus): void
    {
        $ws = $this->createWs('ws', ['public' => true]);
        if (null !== $mask) {
            $this->grantUser($userId, $ws, $mask);
        }

        $this->uploadLogo($ws, $userId);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $em = self::getEntityManager();
        $em->clear();
        $logo = $em->find(Workspace::class, $ws->getId())->getLogoFile();
        if (201 === $expectedStatus) {
            $this->assertNotNull($logo);
        } else {
            $this->assertNull($logo);
        }
    }

    #[DataProvider('accessProvider')]
    public function testDeleteAccess(?string $userId, ?int $mask, int $expectedStatus): void
    {
        $ws = $this->createWs('ws', ['public' => true, 'ownerId' => self::ADMIN]);
        $this->uploadLogo($ws, self::ADMIN);
        $this->assertResponseIsSuccessful();
        if (null !== $mask) {
            $this->grantUser($userId, self::getEntityManager()->find(Workspace::class, $ws->getId()), $mask);
        }

        static::createClient()->request('DELETE', self::iri($ws).'/logo', [
            'headers' => self::authHeaders($userId),
        ]);

        // The upload answers 201, the removal 204
        $this->assertResponseStatusCodeSame(201 === $expectedStatus ? 204 : $expectedStatus);
        $em = self::getEntityManager();
        $em->clear();
        $logo = $em->find(Workspace::class, $ws->getId())->getLogoFile();
        if (201 === $expectedStatus) {
            $this->assertNull($logo);
        } else {
            $this->assertNotNull($logo);
        }
    }

    public function testLogoSizeIsLimitedTo5MB(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        $this->uploadLogo($ws, self::USER, $this->createCompletedUpload(
            base64_decode(self::PNG_BASE64),
            'big.png',
            'image/png',
            5 * 1024 * 1024 + 1,
        ));

        $this->assertResponseStatusCodeSame(400);
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Workspace::class, $ws->getId())->getLogoFile());
    }

    public function testInvalidUploads(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        // Unknown upload
        $this->uploadLogo($ws, self::USER, [
            'uploadId' => '2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99',
            'parts' => [['ETag' => 'x', 'PartNumber' => 1]],
        ]);
        $this->assertResponseStatusCodeSame(404);

        // Not an image
        $this->uploadLogo($ws, self::USER, $this->createCompletedUpload('%PDF-1.4', 'logo.pdf', 'application/pdf'));
        $this->assertResponseStatusCodeSame(400);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Workspace::class, $ws->getId())->getLogoFile());
        $this->assertSame([], $em->getRepository(File::class)->findBy(['workspace' => $ws->getId()]));
    }

    public function testUnknownWorkspace(): void
    {
        $this->uploadLogo('2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);

        static::createClient()->request('DELETE', '/workspaces/2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99/logo', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFlushKeepsTheLogo(): void
    {
        $this->markTestIncomplete('BUG: flushing a workspace drops its logo: WorkspaceDuplicateManager/WorkspaceTemplater (WorkspaceSection) do not copy logoFile, and the old logo File is hard-deleted with the old workspace');

        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $this->uploadLogo($ws, self::USER);
        $this->assertResponseIsSuccessful();

        $response = static::createClient()->request('POST', self::iri($ws).'/flush', [
            'headers' => self::authHeaders(self::USER),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertNotEmpty($response->toArray()['logo'] ?? null);
    }
}
