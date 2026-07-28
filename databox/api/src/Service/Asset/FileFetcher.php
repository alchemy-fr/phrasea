<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Border\UriDownloader;
use App\Entity\Core\File;

readonly class FileFetcher
{
    public function __construct(
        private FileUrlResolver $fileUrlResolver,
        private UriDownloader $fileDownloader,
        private FileStorageManager $fileStorageManager,
    ) {
    }

    public function getFile(File $file, ?string $path = null): string
    {
        if (!$file->isPathPublic()) {
            throw new \InvalidArgumentException(sprintf('File "%s" has a private path', $file->getId()));
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
            throw new \LogicException(sprintf('File "%s" has a private path', $file->getId()));
        }

        if (File::STORAGE_URL !== $file->getStorage()) {
            throw new \LogicException(sprintf('File "%s" is not a remote URL', $file->getId()));
        }

        return $file->localTmpPath = $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), $headers);
    }
}
