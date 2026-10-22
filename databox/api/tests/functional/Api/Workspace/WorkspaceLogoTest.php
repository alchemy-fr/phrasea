<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Consumer\Handler\Image\ProcessImageSet;
use App\Consumer\Handler\Image\ProcessImageSetHandler;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Service\Workspace\TermsPdfGenerator;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\ImageUploadTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST/DELETE /workspaces/{id}/logo, complementing WorkspaceTermsTest::testWorkspaceLogoUpload
 * (happy path): permissions, size limit, processing, replacement cleanup and exposure.
 *
 * The logo is an ImageSet: a raster logo is replaced by an optimized WebP by the
 * ProcessImageSet job (handled right away in tests, the p2 transport being synchronous),
 * an SVG logo is inlined and served as a data URI.
 */
final class WorkspaceLogoTest extends AbstractDataboxTestCase
{
    use ImageUploadTestTrait;
    use WorkspaceTestHelperTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    private function uploadLogo(Workspace|string $workspace, ?string $userId, ?array $multipart = null): void
    {
        static::createClient()->request('POST', self::iri($workspace).'/logo', [
            'headers' => self::authHeaders($userId),
            'json' => [
                'multipart' => $multipart ?? self::multipartPayload($this->createPngUpload()),
            ],
        ]);
    }

    private function findLogo(Workspace $workspace): ?ImageSet
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Workspace::class, $workspace->getId())->getLogo();
    }

    public function testARasterLogoIsReplacedByAnOptimizedWebp(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $upload = $this->createPngUpload(2400, 1200);

        $this->uploadLogo($ws, self::USER, self::multipartPayload($upload));
        $this->assertResponseStatusCodeSame(201);

        $logo = $this->findLogo($ws);
        $this->assertInstanceOf(ImageSet::class, $logo);
        $this->assertNull($logo->getSvgData());
        $this->assertSame($ws->getId(), $logo->getWorkspace()->getId());
        $image = $logo->getImageFile();
        $this->assertSame('image/webp', $image->getType());
        $this->assertSame([2000, 1000], self::storedImageSize($image));
        $this->assertSame('image/webp', $logo->getThumbnailFile()->getType());
        $this->assertSame([512, 256], self::storedImageSize($logo->getThumbnailFile()));
        // The original is gone
        $this->assertNull(self::getEntityManager()->getRepository(File::class)->findOneBy(['path' => $upload->getPath()]));
        $this->assertFalse(self::isStored($upload->getPath()));
    }

    public function testLogoReplacementDeletesThePreviousImage(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        $this->uploadLogo($ws, self::USER);
        $this->assertResponseIsSuccessful();

        $firstLogo = $this->findLogo($ws);
        $this->assertInstanceOf(ImageSet::class, $firstLogo);
        $firstLogoId = $firstLogo->getId();
        $firstFiles = [];
        foreach ([$firstLogo->getImageFile(), $firstLogo->getThumbnailFile()] as $file) {
            $this->assertSame($ws->getId(), $file->getWorkspace()->getId());
            $this->assertTrue(self::isStored($file->getPath()));
            $firstFiles[$file->getId()] = $file->getPath();
        }

        // Upload another logo (SVG is accepted too)
        $svgUpload = $this->createUpload('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', 'logo.svg', 'image/svg+xml');
        $this->uploadLogo($ws, self::USER, self::multipartPayload($svgUpload));
        $this->assertResponseIsSuccessful();

        $secondLogo = $this->findLogo($ws);
        $this->assertNotSame($firstLogoId, $secondLogo->getId());
        $this->assertStringContainsString('<rect', $secondLogo->getSvgData());
        $this->assertNull($secondLogo->getImageFile());
        // An SVG logo has no file: the uploaded object is deleted
        $this->assertFalse(self::isStored($svgUpload->getPath()));
        // The previous logo is removed from the database and the storage
        $em = self::getEntityManager();
        $this->assertNull($em->find(ImageSet::class, $firstLogoId));
        foreach ($firstFiles as $fileId => $path) {
            $this->assertNull($em->find(File::class, $fileId));
            $this->assertFalse(self::isStored($path));
        }

        // Removing the logo deletes its image as well
        static::createClient()->request('DELETE', self::iri($ws).'/logo', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findLogo($ws));
        $this->assertNull(self::getEntityManager()->find(ImageSet::class, $secondLogo->getId()));
    }

    public function testRemovingARasterLogoDeletesItsFiles(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $this->uploadLogo($ws, self::USER);
        $this->assertResponseIsSuccessful();
        $logo = $this->findLogo($ws);
        $paths = [$logo->getImageFile()->getPath(), $logo->getThumbnailFile()->getPath()];

        static::createClient()->request('DELETE', self::iri($ws).'/logo', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull($this->findLogo($ws));
        $this->assertNull(self::getEntityManager()->find(ImageSet::class, $logo->getId()));
        $this->assertSame([], self::getEntityManager()->getRepository(File::class)->findBy(['workspace' => $ws->getId()]));
        foreach ($paths as $path) {
            $this->assertFalse(self::isStored($path));
        }
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
        $logoPath = $this->findLogo($ws)->getImageFile()->getPath();

        $client = static::createClient();
        $response = $client->request('GET', self::iri($ws));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($logoPath, $response->toArray()['logo']);

        $response = $client->request('GET', '/workspaces');
        $this->assertStringContainsString($logoPath, $response->toArray()['member'][0]['logo']);
    }

    public function testAnSvgLogoIsServedAsADataUri(): void
    {
        $ws = $this->createWs('ws', ['public' => true, 'ownerId' => self::USER]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><circle r="4"/></svg>';
        $this->uploadLogo($ws, self::USER, self::multipartPayload($this->createUpload($svg, 'logo.svg', 'image/svg+xml')));
        $this->assertResponseStatusCodeSame(201);

        $logo = static::createClient()->request('GET', self::iri($ws))->toArray()['logo'];
        $prefix = 'data:image/svg+xml;base64,';
        $this->assertStringStartsWith($prefix, $logo);
        $markup = base64_decode(substr($logo, strlen($prefix)));
        $this->assertStringContainsString('<circle', $markup);
        $this->assertStringNotContainsString('script', $markup);
        $this->assertStringNotContainsString('onload', $markup);
        $this->assertSame([], self::getEntityManager()->getRepository(File::class)->findBy(['workspace' => $ws->getId()]));
    }

    public function testAnSvgLogoIsLimitedTo100KB(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><desc>'.str_repeat('a', 100 * 1024).'</desc></svg>';

        $this->uploadLogo($ws, self::USER, self::multipartPayload($this->createUpload($svg, 'logo.svg', 'image/svg+xml')));

        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findLogo($ws));
    }

    public function testTheTermsPdfEmbedsTheProcessedLogo(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $this->uploadLogo($ws, self::USER, self::multipartPayload($this->createPngUpload(64, 64)));
        $this->assertResponseIsSuccessful();
        $this->assertSame('image/webp', $this->findLogo($ws)->getImageFile()->getType());

        $em = self::getEntityManager();
        $terms = new TermsVersion();
        $terms->setWorkspace($em->find(Workspace::class, $ws->getId()));
        $terms->setVersion(1);
        $terms->setText('Terms');

        $pdf = self::getService(TermsPdfGenerator::class)->generatePdf($terms);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'dompdf renders the WebP logo');
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
        $logo = $this->findLogo($ws);
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
        $logo = $this->findLogo($ws);
        if (201 === $expectedStatus) {
            $this->assertNull($logo);
        } else {
            $this->assertNotNull($logo);
        }
    }

    public function testLogoSizeIsLimitedTo5MB(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $upload = $this->createUpload(self::createImage(1, 1, 'png'), 'big.png', 'image/png', 5 * 1024 * 1024 + 1);

        $this->uploadLogo($ws, self::USER, self::multipartPayload($upload));

        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findLogo($ws));
        $this->assertFalse(self::isStored($upload->getPath()), 'the rejected upload is deleted');
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
        $this->uploadLogo($ws, self::USER, self::multipartPayload($this->createUpload('%PDF-1.4', 'logo.pdf', 'application/pdf')));
        $this->assertResponseStatusCodeSame(400);

        $this->assertNull($this->findLogo($ws));
        $this->assertSame([], self::getEntityManager()->getRepository(File::class)->findBy(['workspace' => $ws->getId()]));
        $this->assertSame([], self::getEntityManager()->getRepository(ImageSet::class)->findBy(['workspace' => $ws->getId()]));
    }

    public function testTheLogoJobIsDispatchedOnce(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);
        $client = static::createClient();
        $client->disableReboot();
        $transport = self::interceptP2();

        $client->request('POST', self::iri($ws).'/logo', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['multipart' => self::multipartPayload($this->createPngUpload(600, 600))],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $messages = self::sentProcessImageSetMessages($transport);
        $this->assertCount(1, $messages);
        self::stopInterceptingP2();

        // Until the job has run, the logo is the original
        $logo = $this->findLogo($ws);
        $this->assertSame($logo->getId(), $messages[0]->getImageSetId());
        $this->assertSame('image/png', $logo->getImageFile()->getType());
        $this->assertNull($logo->getThumbnailFile());

        $handler = self::getService(ProcessImageSetHandler::class);
        $handler(new ProcessImageSet($logo->getId()));
        $logo = $this->findLogo($ws);
        $this->assertSame('image/webp', $logo->getImageFile()->getType());
        $imageFileId = $logo->getImageFile()->getId();

        // Running the job again does not process the image twice
        $handler(new ProcessImageSet($logo->getId()));
        $this->assertSame($imageFileId, $this->findLogo($ws)->getImageFile()->getId());
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
        $this->markTestIncomplete('BUG: flushing a workspace drops its logo: WorkspaceDuplicateManager/WorkspaceTemplater (WorkspaceSection) do not copy the logo, and the old logo image is hard-deleted with the old workspace');

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
