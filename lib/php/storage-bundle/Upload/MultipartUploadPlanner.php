<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Decides how a file is split into S3 parts.
 *
 * The chunking policy lives on the server so that every client (web apps,
 * server-to-server pushes) gets the same, storage-compatible plan and can
 * receive all the presigned part URLs at once.
 */
final readonly class MultipartUploadPlanner
{
    public function __construct(
        private int $minChunkSize,
        private int $maxChunkSize,
        private int $maxPartNumber,
        private int $maxObjectSize,
    ) {
        if ($minChunkSize < 1 || $maxChunkSize < $minChunkSize || $maxPartNumber < 1 || $maxObjectSize < 1) {
            throw new \InvalidArgumentException('Invalid multipart upload limits');
        }
    }

    /**
     * Smallest chunk size (>= the configured minimum) that fits the file in
     * the allowed number of parts.
     */
    public function resolveChunkSize(int $size): int
    {
        if ($size < 0) {
            throw new BadRequestHttpException('File size must be positive');
        }
        if ($size > $this->maxObjectSize) {
            throw new BadRequestHttpException(sprintf('File size %d exceeds the maximum allowed size of %d bytes', $size, $this->maxObjectSize));
        }

        $chunkSize = max($this->minChunkSize, (int) ceil($size / $this->maxPartNumber));
        if ($chunkSize > $this->maxChunkSize) {
            throw new BadRequestHttpException(sprintf('File of %d bytes cannot be uploaded: it would need parts of %d bytes, above the maximum of %d bytes for %d parts', $size, $chunkSize, $this->maxChunkSize, $this->maxPartNumber));
        }

        return $chunkSize;
    }

    /**
     * An empty file still needs one (empty) part for S3 to complete the upload.
     */
    public function getPartCount(int $size, int $chunkSize): int
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be positive');
        }

        return max(1, (int) ceil($size / $chunkSize));
    }
}
