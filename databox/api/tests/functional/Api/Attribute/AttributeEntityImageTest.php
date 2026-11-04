<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\EntityAttributeType;
use App\Consumer\Handler\File\DeleteFilesIfOrphan;
use App\Consumer\Handler\Image\ProcessImageSet;
use App\Consumer\Handler\Image\ProcessImageSetHandler;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\ImageUploadTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST/DELETE /attribute-entities/{id}/image: the photo of an entity list value.
 *
 * A raster photo is kept as uploaded until the ProcessImageSet job replaces it with an
 * optimized WebP (≤ 2000 px) and builds a WebP thumbnail (≤ 256 px). The p2 transport is
 * synchronous in tests: the job runs right after the upload unless the transport is intercepted.
 * An SVG photo is inlined and served as a data URI.
 */
final class AttributeEntityImageTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;
    use ImageUploadTestTrait;

    private const string DATA_URI_PREFIX = 'data:image/svg+xml;base64,';

    private Workspace $workspace;
    private EntityList $list;
    private AttributeEntity $entity;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member.
     */
    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->list = $this->createEntityList(['name' => 'People']);
        $this->entity = $this->createEntity($this->list, 'Alice');
    }

    private function imageUri(): string
    {
        return '/attribute-entities/'.$this->entity->getId().'/image';
    }

    private function uploadImage(?string $userId, ?array $multipart = null): array
    {
        return $this->api('POST', $this->imageUri(), $userId, [
            'multipart' => $multipart ?? self::multipartPayload($this->createPngUpload()),
        ])->toArray(false);
    }

    private function findImage(): ?ImageSet
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(AttributeEntity::class, $this->entity->getId())?->getImage();
    }

    /**
     * @return array<string, string> file ID => storage path
     */
    private static function filesOf(ImageSet $imageSet): array
    {
        $files = [];
        foreach ([$imageSet->getImageFile(), $imageSet->getThumbnailFile()] as $file) {
            if (null !== $file) {
                $files[$file->getId()] = $file->getPath();
            }
        }

        return $files;
    }

    /**
     * @param array<string, string> $files file ID => storage path
     */
    private function assertFilesAreDeleted(array $files): void
    {
        $this->assertNotEmpty($files);
        $em = self::getEntityManager();
        foreach ($files as $fileId => $path) {
            $this->assertNull($em->find(File::class, $fileId), 'the file is removed');
            $this->assertFalse(self::isStored($path), 'the file is removed from the storage');
        }
    }

    public function testARasterImageIsKeptAsUploadedUntilTheJob(): void
    {
        $this->setUpScene();
        $client = static::createClient();
        $client->disableReboot();
        $transport = self::interceptP2();
        $upload = $this->createPngUpload(3000, 1500, 'alice.png');

        $response = $client->request('POST', $this->imageUri(), [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER)],
            'json' => ['multipart' => self::multipartPayload($upload)],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame($this->entity->getId(), $data['id']);
        // Before the job, both URLs are the original's
        $this->assertStringContainsString($upload->getPath(), $data['image']);
        $this->assertStringContainsString($upload->getPath(), $data['thumbnail']);

        $messages = self::sentProcessImageSetMessages($transport);
        $this->assertCount(1, $messages);
        $imageSet = $this->findImage();
        $this->assertSame($imageSet->getId(), $messages[0]->getImageSetId());
        $this->assertSame($this->workspace->getId(), $imageSet->getWorkspace()->getId());
        $original = $imageSet->getImageFile();
        $this->assertSame('image/png', $original->getType());
        $this->assertSame($upload->getPath(), $original->getPath());
        $this->assertNull($imageSet->getThumbnailFile());

        self::stopInterceptingP2();
        self::getService(ProcessImageSetHandler::class)(new ProcessImageSet($imageSet->getId()));

        $imageSet = $this->findImage();
        $image = $imageSet->getImageFile();
        $thumbnail = $imageSet->getThumbnailFile();
        $this->assertSame('image/webp', $image->getType());
        $this->assertSame('image/webp', $thumbnail->getType());
        $this->assertSame([2000, 1000], self::storedImageSize($image));
        $this->assertSame([256, 128], self::storedImageSize($thumbnail));
        $this->assertLessThan($image->getSize(), $thumbnail->getSize());
        // The original is replaced
        $this->assertNull(self::getEntityManager()->find(File::class, $original->getId()));
        $this->assertFalse(self::isStored($upload->getPath()));

        $data = $this->api('GET', '/attribute-entities/'.$this->entity->getId(), self::USER)->toArray();
        $this->assertStringContainsString($image->getPath(), $data['image']);
        $this->assertStringContainsString($thumbnail->getPath(), $data['thumbnail']);
    }

    public function testASmallImageIsNotUpscaled(): void
    {
        $this->setUpScene();

        $this->uploadImage(self::USER, self::multipartPayload($this->createPngUpload(200, 150)));
        $this->assertResponseStatusCodeSame(201);

        $imageSet = $this->findImage();
        $this->assertSame([200, 150], self::storedImageSize($imageSet->getImageFile()));
        $this->assertSame([200, 150], self::storedImageSize($imageSet->getThumbnailFile()));
    }

    public function testTheJobIsANoopOnARemovedOrProcessedImage(): void
    {
        $this->setUpScene();

        self::getService(ProcessImageSetHandler::class)(new ProcessImageSet('2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99'));

        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $imageSet = $this->findImage();
        $files = self::filesOf($imageSet);
        self::getService(ProcessImageSetHandler::class)(new ProcessImageSet($imageSet->getId()));
        $this->assertSame($files, self::filesOf($this->findImage()));
    }

    public function testReplacingTheImageRemovesThePreviousOne(): void
    {
        $this->setUpScene();
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $first = $this->findImage();
        $firstFiles = self::filesOf($first);
        $this->assertCount(2, $firstFiles);

        $client = static::createClient();
        $client->disableReboot();
        $transport = self::interceptP2();
        $client->request('POST', $this->imageUri(), [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER)],
            'json' => ['multipart' => self::multipartPayload($this->createPngUpload())],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->assertNotSame($first->getId(), $this->findImage()->getId());
        $this->assertNull(self::getEntityManager()->find(ImageSet::class, $first->getId()));
        // The files of the previous image are scheduled for deletion
        $scheduled = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof DeleteFilesIfOrphan) {
                $scheduled = [...$scheduled, ...$message->getIds()];
            }
        }
        $this->assertEqualsCanonicalizing(array_keys($firstFiles), $scheduled);
        self::stopInterceptingP2();
    }

    public function testRemovingTheImage(): void
    {
        $this->setUpScene();
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $imageSet = $this->findImage();
        $files = self::filesOf($imageSet);

        $this->api('DELETE', $this->imageUri(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull($this->findImage());
        $this->assertNull(self::getEntityManager()->find(ImageSet::class, $imageSet->getId()));
        $this->assertFilesAreDeleted($files);
        $data = $this->api('GET', '/attribute-entities/'.$this->entity->getId(), self::USER)->toArray();
        $this->assertNull($data['image'] ?? null);
        $this->assertNull($data['thumbnail'] ?? null);

        // Removing a missing image is a no-op
        $this->api('DELETE', $this->imageUri(), self::USER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeletingTheValueRemovesItsImage(): void
    {
        $this->setUpScene();
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $imageSet = $this->findImage();
        $files = self::filesOf($imageSet);

        $this->api('DELETE', '/attribute-entities/'.$this->entity->getId(), self::USER);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull(self::getEntityManager()->find(ImageSet::class, $imageSet->getId()));
        $this->assertFilesAreDeleted($files);
    }

    public function testAnSvgImageIsInlined(): void
    {
        $this->setUpScene();
        $client = static::createClient();
        $client->disableReboot();
        $transport = self::interceptP2();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
            .'<script>alert(2)</script>'
            .'<image xlink:href="https://evil.example/x.png" width="1" height="1"/>'
            .'<circle r="4" onclick="alert(3)"/></svg>';
        $upload = $this->createUpload($svg, 'alice.svg', 'image/svg+xml');

        $data = $client->request('POST', $this->imageUri(), [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER)],
            'json' => ['multipart' => self::multipartPayload($upload)],
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);

        $this->assertStringStartsWith(self::DATA_URI_PREFIX, $data['image']);
        $this->assertSame($data['image'], $data['thumbnail']);
        $markup = base64_decode(substr($data['image'], strlen(self::DATA_URI_PREFIX)));
        $this->assertStringContainsString('<circle', $markup);
        foreach (['script', 'onload', 'onclick', 'evil.example'] as $removed) {
            $this->assertStringNotContainsString($removed, $markup);
        }

        $imageSet = $this->findImage();
        $this->assertSame($markup, $imageSet->getSvgData());
        $this->assertNull($imageSet->getImageFile());
        $this->assertSame([], self::getEntityManager()->getRepository(File::class)->findBy(['workspace' => $this->workspace->getId()]));
        $this->assertFalse(self::isStored($upload->getPath()), 'the uploaded object is deleted');
        $this->assertSame([], self::sentProcessImageSetMessages($transport), 'an SVG is not processed');
        self::stopInterceptingP2();
    }

    public function testAnSvgImageIsLimitedTo100KB(): void
    {
        $this->setUpScene();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><desc>'.str_repeat('a', 100 * 1024).'</desc></svg>';

        $this->uploadImage(self::USER, self::multipartPayload($this->createUpload($svg, 'big.svg', 'image/svg+xml')));

        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findImage());
    }

    public function testAnInvalidSvgIsRejected(): void
    {
        $this->setUpScene();

        $this->uploadImage(self::USER, self::multipartPayload($this->createUpload('not an svg', 'bad.svg', 'image/svg+xml')));

        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findImage());
    }

    public function testInvalidUploads(): void
    {
        $this->setUpScene();

        // Not an image
        $this->uploadImage(self::USER, self::multipartPayload($this->createUpload('%PDF-1.4', 'alice.pdf', 'application/pdf')));
        $this->assertResponseStatusCodeSame(400);

        // Too big
        $upload = $this->createUpload(self::createImage(1, 1, 'jpeg'), 'alice.jpg', 'image/jpeg', 20 * 1024 * 1024 + 1);
        $this->uploadImage(self::USER, self::multipartPayload($upload));
        $this->assertResponseStatusCodeSame(400);
        $this->assertFalse(self::isStored($upload->getPath()), 'the rejected upload is deleted');

        // Unknown upload
        $this->uploadImage(self::USER, [
            'uploadId' => '2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99',
            'parts' => [['ETag' => 'x', 'PartNumber' => 1]],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $this->assertNull($this->findImage());
        $em = self::getEntityManager();
        $this->assertSame([], $em->getRepository(File::class)->findBy(['workspace' => $this->workspace->getId()]));
        $this->assertSame([], $em->getRepository(ImageSet::class)->findBy(['workspace' => $this->workspace->getId()]));
    }

    public function testUnknownValue(): void
    {
        $this->setUpScene();

        $this->api('POST', '/attribute-entities/2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99/image', self::USER, [
            'multipart' => self::multipartPayload($this->createPngUpload()),
        ]);
        $this->assertResponseStatusCodeSame(404);
        $this->api('DELETE', '/attribute-entities/2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99/image', self::USER);
        $this->assertResponseStatusCodeSame(404);
    }

    public static function accessProvider(): array
    {
        return [
            // user, expected status of the upload (the removal answers 204 instead of 201)
            'anonymous' => [null, 401],
            'member' => [self::OTHER, 403],
            'list editor' => [self::USER, 201],
        ];
    }

    #[DataProvider('accessProvider')]
    public function testUploadAccess(?string $userId, int $expectedStatus): void
    {
        $this->setUpScene();

        $this->uploadImage($userId);

        $this->assertResponseStatusCodeSame($expectedStatus);
        if (201 === $expectedStatus) {
            $this->assertNotNull($this->findImage());
        } else {
            $this->assertNull($this->findImage());
        }
    }

    #[DataProvider('accessProvider')]
    public function testDeleteAccess(?string $userId, int $expectedStatus): void
    {
        $this->setUpScene();
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);

        $this->api('DELETE', $this->imageUri(), $userId);

        $this->assertResponseStatusCodeSame(201 === $expectedStatus ? 204 : $expectedStatus);
        if (201 === $expectedStatus) {
            $this->assertNull($this->findImage());
        } else {
            $this->assertNotNull($this->findImage());
        }
    }

    public function testTheValuesListCarriesTheImages(): void
    {
        $this->setUpScene();
        $this->createEntity($this->list, 'Bob');
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $thumbnailPath = $this->findImage()->getThumbnailFile()->getPath();

        $members = $this->api('GET', '/attribute-entities', self::USER, options: [
            'query' => ['list' => $this->list->getId()],
        ])->toArray()['member'];

        $byValue = array_column($members, null, 'value');
        $this->assertStringContainsString($thumbnailPath, $byValue['Alice']['thumbnail']);
        $this->assertNotEmpty($byValue['Alice']['image']);
        $this->assertNull($byValue['Bob']['thumbnail'] ?? null);
    }

    public function testAnAssetAttributeCarriesTheThumbnail(): void
    {
        $this->setUpScene();
        $definition = $this->createAttributeDefinition(['name' => 'Person', 'type' => EntityAttributeType::NAME, 'list' => $this->list]);
        $asset = $this->createAsset([
            'ownerId' => self::USER,
            'attributes' => [
                ['definition' => $definition, 'value' => $this->entity->getId()],
            ],
        ]);
        $this->uploadImage(self::USER);
        $this->assertResponseStatusCodeSame(201);
        $thumbnailPath = $this->findImage()->getThumbnailFile()->getPath();

        $members = $this->api('GET', '/attributes', self::USER, options: [
            'query' => ['assetId' => $asset->getId()],
        ])->toArray()['member'];

        $this->assertCount(1, $members);
        $this->assertSame($this->entity->getId(), $members[0]['value']['id']);
        $this->assertStringContainsString($thumbnailPath, $members[0]['value']['thumbnail']);
        $this->assertArrayNotHasKey('image', $members[0]['value'], 'only the thumbnail goes with the attribute');
    }
}
