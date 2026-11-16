<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;

/**
 * Multipart uploads whose parts were sent, left for the API to complete when a resource
 * uses them. The file is put in the local test storage, where LocalS3Handler finds it.
 */
trait MultipartUploadTestTrait
{
    private function createUpload(string $content, string $name, string $type, string $ownerId = KeycloakClientTestMock::USER_UID): MultipartUpload
    {
        $em = self::getEntityManager();

        $upload = new MultipartUpload();
        $upload->setOwnerId($ownerId);
        $upload->setFilename($name);
        $upload->setType($type);
        $upload->setSize(strlen($content));
        $upload->setPath(sprintf('test-uploads/%s.%s', uniqid(), pathinfo($name, PATHINFO_EXTENSION)));
        $upload->setUploadId('test-'.uniqid());
        $em->persist($upload);
        $em->flush();

        self::getService(FileStorageManager::class)->store($upload->getPath(), $content);

        return $upload;
    }

    /**
     * The "multipart" payload completing the upload.
     */
    private static function multipartPayload(MultipartUpload $upload): array
    {
        return [
            'uploadId' => $upload->getId(),
            'parts' => [
                ['ETag' => 'test-etag', 'PartNumber' => 1],
            ],
        ];
    }
}
