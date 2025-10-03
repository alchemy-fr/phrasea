<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Aws\Api\DateTimeResult;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UploadManager
{
    public function __construct(
        private S3Client $client,
        private string $uploadBucket,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private FileValidator $fileValidator,
        private string $pathPrefix,
    ) {
    }

    public function prepareMultipartUpload(string $path, string $contentType)
    {
        $this->fileValidator->validateFile($path, $contentType);

        return $this->client->createMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            'ContentType' => $contentType,
        ]);
    }

    public function cancelMultipartUpload(string $path, string $uploadId): void
    {
        $this->client->abortMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            'UploadId' => $uploadId,
        ]);
    }

    public function getSignedUrl(string $uploadId, string $path, int $partNumber): string
    {
        $params = [
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            'PartNumber' => $partNumber,
            'UploadId' => $uploadId,
        ];

        $cmd = $this->client->getCommand('UploadPart', $params);

        $request = $this->client->createPresignedRequest($cmd, '+30 minutes');

        return (string) $request->getUri();
    }

    public function markComplete(string $uploadId, string $filename, array $parts): void
    {
        $params = [
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$filename,
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
            'UploadId' => $uploadId,
        ];

        $this->client->completeMultipartUpload($params);
    }

    public function createPutObjectSignedURL(string $path, string $contentType): string
    {
        $command = $this->client->getCommand('PutObject', [
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            'ContentType' => $contentType,
        ]);
        $request = $this->client->createPresignedRequest($command, '+30 minutes');

        return (string) $request->getUri();
    }

    public function pruneParts(): void
    {
        $result = $this->client->listMultipartUploads([
            'Bucket' => $this->uploadBucket,
            'MaxUploads' => 100,
            // Uncomment this line to test with Minio (see https://github.com/minio/minio/issues/7632#issuecomment-490959779)
            //            'Prefix' => 'fc/6e/fc6e0e4d-aad6-4f7d-9133-682607991072.jpg',
        ]);

        if (empty($result['Uploads'])) {
            $this->logger->info('No upload to remove');

            return;
        }

        $this->logger->info(sprintf('%s upload(s) to remove', count($result['Uploads'])));

        $gracePeriod = 3600 * 24 * 3;

        foreach ($result['Uploads'] as $upload) {
            /** @var DateTimeResult $initiated */
            $initiated = $upload['Initiated'];
            if ($initiated->getTimestamp() < time() - $gracePeriod) {
                $this->cancelMultipartUpload($upload['Key'], $upload['UploadId']);
                $this->logger->info(sprintf('Removed: upload %s at %s', $upload['UploadId'], $upload['Key']));
            }
        }
    }

    public function handleMultipartUpload(Request $request): MultipartUpload
    {
        $multipart = $request->request->all('multipart');

        foreach ([
            'parts',
            'uploadId',
        ] as $key) {
            if (empty($multipart[$key])) {
                throw new BadRequestHttpException(sprintf('Missing multipart param: %s', $key));
            }
        }

        $multipartUpload = $this->em->getRepository(MultipartUpload::class)->find($multipart['uploadId']);
        if (!$multipartUpload instanceof MultipartUpload) {
            throw new NotFoundHttpException('Upload not found');
        }

        $this->markComplete(
            $multipartUpload->getUploadId(),
            $multipartUpload->getPath(),
            $multipart['parts']
        );

        $multipartUpload->setComplete(true);
        $this->em->persist($multipartUpload);

        return $multipartUpload;
    }
}
