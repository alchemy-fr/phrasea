<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Api\Dto\PartInput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UploadManager
{
    /**
     * Part URLs are handed out all at once when the upload is created, so they
     * must outlive the whole transfer (clients refresh them past this point).
     */
    public const string PART_URL_TTL = '+3 hours';

    /**
     * Default minimum age of the uploads aborted by pruneParts(), well beyond
     * PART_URL_TTL: a client may still be sending the parts of a younger one.
     */
    public const int PRUNE_MIN_AGE = 24 * 3600;

    /**
     * Lowest minimum age accepted by pruneParts(): the lifetime of the part URLs.
     */
    public const int PRUNE_MIN_AGE_LIMIT = 3 * 3600;

    /**
     * Lifetime, in seconds, of the URL of a single PUT (createPutObjectSignedURL()):
     * the object it points to can change until then.
     */
    public const int PUT_URL_TTL = 1800;

    public function __construct(
        private S3Client $client,
        private string $uploadBucket,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private FileValidator $fileValidator,
        private string $pathPrefix,
        private MultipartUploadPlanner $planner,
    ) {
    }

    public function prepareMultipartUpload(string $path, string $contentType)
    {
        $this->fileValidator->validateFile($path, $contentType);

        return $this->client->createMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            // Never the declared type, which nothing checked yet: the signed URLs force the
            // served type (InlineContentTypePolicy), a CDN dropping "response-content-type" falls back to this one.
            'ContentType' => InlineContentTypePolicy::ATTACHMENT_CONTENT_TYPE,
        ]);
    }

    public function cancelMultipartUpload(string $path, string $uploadId): void
    {
        $this->abortUpload($this->pathPrefix.$path, $uploadId);
    }

    /**
     * @param string $key the S3 key, path prefix included
     */
    private function abortUpload(string $key, string $uploadId): void
    {
        $this->client->abortMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    public function getSignedUrl(string $uploadId, string $path, int $partNumber): string
    {
        return $this->getSignedUrls($uploadId, $path, $partNumber, $partNumber)[$partNumber];
    }

    /**
     * Presigned PUT URLs for parts $from..$to (inclusive), keyed by part number.
     * Signing is local (no S3 round trip), so handing out thousands is cheap.
     *
     * @return array<int, string>
     */
    public function getSignedUrls(string $uploadId, string $path, int $from, int $to): array
    {
        if ($from < 1 || $to < $from) {
            throw new \InvalidArgumentException(sprintf('Invalid part range %d..%d', $from, $to));
        }

        $urls = [];
        for ($partNumber = $from; $partNumber <= $to; ++$partNumber) {
            $cmd = $this->client->getCommand('UploadPart', [
                'Bucket' => $this->uploadBucket,
                'Key' => $this->pathPrefix.$path,
                'PartNumber' => $partNumber,
                'UploadId' => $uploadId,
            ]);

            $urls[$partNumber] = (string) $this->client->createPresignedRequest($cmd, self::PART_URL_TTL)->getUri();
        }

        return $urls;
    }

    /**
     * @param PartInput[] $parts
     */
    public function markComplete(string $uploadId, string $filename, array $parts): void
    {
        // S3 requires the parts in ascending PartNumber order.
        usort($parts, fn (PartInput $a, PartInput $b): int => (int) $a->PartNumber <=> (int) $b->PartNumber);

        $params = [
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$filename,
            'MultipartUpload' => [
                'Parts' => array_map(function (PartInput $part): array {
                    return [
                        'ETag' => $part->ETag,
                        'PartNumber' => (int) $part->PartNumber,
                    ];
                }, $parts),
            ],
            'UploadId' => $uploadId,
        ];

        $this->client->completeMultipartUpload($params);
    }

    /**
     * A presigned PUT binds neither the size nor the type of the object (the SDK
     * does not sign the Content-Type): once the URL expired (PUT_URL_TTL), the
     * caller checks the stored object (getStoredSize(), validateStoredContent()).
     */
    public function createPutObjectSignedURL(string $path, string $contentType): string
    {
        $this->fileValidator->validateFile($path, $contentType);

        $command = $this->client->getCommand('PutObject', [
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
        ]);
        $request = $this->client->createPresignedRequest($command, sprintf('+%d seconds', self::PUT_URL_TTL));

        return (string) $request->getUri();
    }

    /**
     * Size of the stored object.
     *
     * @throws S3Exception "NotFound" when there is no object at $path
     */
    public function getStoredSize(string $path): int
    {
        return (int) $this->client->headObject([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
        ])->get('ContentLength');
    }

    /**
     * Checks the content of the stored object, read from its first bytes, as
     * FileValidator::validateFileContent() does for a local file.
     *
     * @param int $size the size of the stored object (getStoredSize())
     *
     * @return string the type of the content, to use instead of the declared one
     *
     * @throws BadRequestHttpException
     */
    public function validateStoredContent(string $path, string $filename, ?string $declaredType, int $size): string
    {
        // A range request on an empty object is refused by S3
        $head = 0 === $size ? '' : (string) $this->client->getObject([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
            'Range' => sprintf('bytes=0-%d', ContentTypeDetector::HEAD_SIZE - 1),
        ])->get('Body');

        $detector = new ContentTypeDetector();
        $type = $detector->resolveType($declaredType, $detector->checkBuffer($head, $declaredType), $filename);

        // The declared type was checked against the allowed types when the upload was prepared
        if ($type !== InlineContentTypePolicy::normalize($declaredType)) {
            $this->fileValidator->validateFile($filename, $type);
        }

        return $type;
    }

    public function deleteStoredObject(string $path): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->uploadBucket,
            'Key' => $this->pathPrefix.$path,
        ]);
    }

    /**
     * Aborts the incomplete S3 multipart uploads of the application initiated
     * at least $minAge seconds ago, then deletes the uploads of that age never
     * used by a resource.
     *
     * @return array{aborted: int, deleted: int}
     */
    public function pruneParts(int $minAge = self::PRUNE_MIN_AGE): array
    {
        if ($minAge < self::PRUNE_MIN_AGE_LIMIT) {
            throw new \InvalidArgumentException(sprintf('The minimum age of the uploads to prune must be at least %d seconds (the lifetime of the part URLs), got %d', self::PRUNE_MIN_AGE_LIMIT, $minAge));
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d seconds', $minAge));
        $aborted = $this->abortStaleUploads($threshold);
        $deleted = $this->deleteUnusedUploads($threshold);

        $this->logger->info(sprintf('%d upload(s) aborted on S3, %d unused upload(s) deleted', $aborted, $deleted));

        return ['aborted' => $aborted, 'deleted' => $deleted];
    }

    /**
     * Aborts the S3 multipart uploads under the path prefix of the application
     * initiated before $initiatedBefore, whether a MultipartUpload row refers to
     * them or not (the row may never have been flushed).
     *
     * With an empty path prefix, every multipart upload of the bucket is
     * concerned: the bucket must not be shared with another application.
     *
     * @return int the number of aborted uploads
     */
    public function abortStaleUploads(\DateTimeImmutable $initiatedBefore): int
    {
        $params = ['Bucket' => $this->uploadBucket];
        if ('' !== $this->pathPrefix) {
            $params['Prefix'] = $this->pathPrefix;
        }

        $aborted = 0;
        foreach ($this->client->getPaginator('ListMultipartUploads', $params) as $result) {
            foreach ($result['Uploads'] ?? [] as $upload) {
                /** @var \DateTimeInterface $initiated */
                $initiated = $upload['Initiated'];
                if ($initiated >= $initiatedBefore) {
                    continue;
                }

                try {
                    $this->abortUpload($upload['Key'], $upload['UploadId']);
                } catch (S3Exception $e) {
                    if ('NoSuchUpload' !== $e->getAwsErrorCode()) {
                        throw $e;
                    }

                    $this->logger->warning(sprintf('Upload %s at %s was already gone', $upload['UploadId'], $upload['Key']));

                    continue;
                }

                ++$aborted;
                $this->logger->info(sprintf('Aborted upload %s at %s', $upload['UploadId'], $upload['Key']));
            }
        }

        return $aborted;
    }

    /**
     * Deletes the uploads created before $createdBefore that no resource used.
     *
     * @return int the number of deleted uploads
     */
    public function deleteUnusedUploads(\DateTimeImmutable $createdBefore): int
    {
        // A DQL delete skips the listeners: the S3 uploads are aborted by abortStaleUploads().
        return (int) $this->em->createQueryBuilder()
            ->delete(MultipartUpload::class, 'u')
            ->andWhere('u.complete = false')
            ->andWhere('u.consumedAt IS NULL')
            ->andWhere('u.createdAt < :createdBefore')
            ->setParameter('createdBefore', $createdBefore, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Completes the upload on S3 for its owner, then checks that the stored
     * object has the declared size and a content consistent with the declared
     * type: the parts are sent straight to S3, so their content is only known
     * here. A refused object is deleted with the upload.
     *
     * The type of the returned upload is then the one of the content.
     *
     * An upload is used once: it is claimed before anything is done on S3,
     * and released if S3 refuses the completion so that the client can retry.
     *
     * @param string $ownerId user identifier of the token completing the upload
     */
    public function handleMultipartUpload(MultipartUploadInput $multipart, string $ownerId): MultipartUpload
    {
        $multipartUpload = $this->em->getRepository(MultipartUpload::class)->find($multipart->uploadId);
        if (!$multipartUpload instanceof MultipartUpload) {
            throw new NotFoundHttpException(sprintf('Multipart upload with id "%s" not found', $multipart->uploadId));
        }

        $this->claim($multipartUpload, $ownerId);

        try {
            $this->markComplete(
                $multipartUpload->getUploadId(),
                $multipartUpload->getPath(),
                $multipart->parts
            );
        } catch (\Throwable $e) {
            $this->release($multipartUpload);

            throw $e;
        }

        $size = $this->assertUploadedSize($multipartUpload);

        try {
            $type = $this->validateStoredContent(
                $multipartUpload->getPath(),
                (string) $multipartUpload->getFilename(),
                $multipartUpload->getType(),
                $size,
            );
        } catch (BadRequestHttpException $e) {
            $this->discard($multipartUpload);

            throw $e;
        }

        $multipartUpload->setType($type);
        $multipartUpload->setComplete(true);
        $this->em->persist($multipartUpload);

        return $multipartUpload;
    }

    /**
     * Reserves the upload for the current request. The conditional update is
     * executed right away, outside the unit of work: among concurrent
     * requests, only the one which flips "consumed_at" gets the upload.
     *
     * @param string $ownerId user identifier of the token using the upload
     */
    public function claim(MultipartUpload $multipartUpload, string $ownerId): void
    {
        self::denyUnlessOwner($multipartUpload, $ownerId);

        if ($multipartUpload->isComplete() || $multipartUpload->isConsumed()) {
            throw self::createAlreadyUsedException($multipartUpload);
        }

        $now = new \DateTimeImmutable();
        $claimed = $this->em->createQueryBuilder()
            ->update(MultipartUpload::class, 'u')
            ->set('u.consumedAt', ':now')
            ->andWhere('u.id = :id')
            ->andWhere('u.consumedAt IS NULL')
            ->andWhere('u.complete = false')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $multipartUpload->getId())
            ->getQuery()
            ->execute();

        if (1 !== $claimed) {
            throw self::createAlreadyUsedException($multipartUpload);
        }

        $multipartUpload->setConsumedAt($now);
    }

    private function release(MultipartUpload $multipartUpload): void
    {
        $this->em->createQueryBuilder()
            ->update(MultipartUpload::class, 'u')
            ->set('u.consumedAt', 'NULL')
            ->andWhere('u.id = :id')
            ->setParameter('id', $multipartUpload->getId())
            ->getQuery()
            ->execute();

        $multipartUpload->setConsumedAt(null);
    }

    private static function createAlreadyUsedException(MultipartUpload $multipartUpload): BadRequestHttpException
    {
        return new BadRequestHttpException(sprintf('Multipart upload "%s" was already used', $multipartUpload->getId()));
    }

    public static function denyUnlessOwner(MultipartUpload $multipartUpload, string $ownerId): void
    {
        if (!$multipartUpload->isOwnedBy($ownerId)) {
            throw new AccessDeniedHttpException(sprintf('Multipart upload "%s" does not belong to you', $multipartUpload->getId()));
        }
    }

    /**
     * @return int the size of the stored object
     */
    private function assertUploadedSize(MultipartUpload $multipartUpload): int
    {
        $size = $this->getStoredSize($multipartUpload->getPath());

        $maxFileSize = $this->planner->getMaxFileSize();
        if ($size === $multipartUpload->getSize() && $size <= $maxFileSize) {
            return $size;
        }

        $this->discard($multipartUpload);

        if ($size > $maxFileSize) {
            throw new BadRequestHttpException(sprintf('Uploaded file size %d exceeds the maximum allowed size of %d bytes', $size, $maxFileSize));
        }

        throw new BadRequestHttpException(sprintf('Uploaded file size %d does not match the declared size %d', $size, $multipartUpload->getSize()));
    }

    /**
     * Deletes a completed upload refused afterwards, and its object.
     */
    private function discard(MultipartUpload $multipartUpload): void
    {
        $this->deleteStoredObject($multipartUpload->getPath());
        // A DQL delete skips the listeners: the S3 upload is already completed, there is nothing to abort.
        $this->em->createQueryBuilder()
            ->delete(MultipartUpload::class, 'u')
            ->andWhere('u.id = :id')
            ->setParameter('id', $multipartUpload->getId())
            ->getQuery()
            ->execute();
    }
}
