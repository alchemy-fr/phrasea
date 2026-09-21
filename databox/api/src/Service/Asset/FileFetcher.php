<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Border\UriDownloader;
use App\Entity\Core\File;
use App\Service\Asset\Exception\PrivateFileException;

readonly class FileFetcher
{
    public function __construct(
        private FileUrlResolver $fileUrlResolver,
        private UriDownloader $fileDownloader,
        private FileStorageManager $fileStorageManager,
    ) {
    }

    /**
     * Whether the file content can be retrieved by the platform: either stored on
     * the main storage, or a remote URL declared as publicly reachable.
     */
    public function isFetchable(File $file): bool
    {
        return File::STORAGE_S3_MAIN === $file->getStorage() || $file->isPathPublic();
    }

    public function getFile(File $file, ?string $path = null): string
    {
        if (!$this->isFetchable($file)) {
            throw new PrivateFileException($file);
        }

        if (null === $path && $file->localTmpPath && file_exists($file->localTmpPath)) {
            return $file->localTmpPath;
        }

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $path ??= sys_get_temp_dir().'/'.uniqid('fetch-file');
            $stream = $this->fileStorageManager->getStream($file->getPath());
            file_put_contents($path, $stream);
            fclose($stream);

            return $file->localTmpPath = $path;
        }

        return $file->localTmpPath = $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), path: $path);
    }

    public function downloadFile(File $file, array &$headers = []): string
    {
        if (!$file->isPathPublic()) {
            throw new PrivateFileException($file);
        }

        if (File::STORAGE_URL !== $file->getStorage()) {
            throw new \LogicException(sprintf('File "%s" is not a remote URL', $file->getId()));
        }

        return $file->localTmpPath = $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), $headers);
    }
}
