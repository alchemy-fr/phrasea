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

    /**
     * The first bytes of the file content, without fetching all of it.
     */
    public function getHead(File $file, int $length): string
    {
        if (!$this->isFetchable($file)) {
            throw new PrivateFileException($file);
        }

        if (null !== $file->localTmpPath && file_exists($file->localTmpPath)) {
            return (string) file_get_contents($file->localTmpPath, false, null, 0, $length);
        }

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $stream = $this->fileStorageManager->getStream($file->getPath());
            try {
                $head = '';
                while (strlen($head) < $length && !feof($stream)) {
                    $chunk = fread($stream, $length - strlen($head));
                    if (false === $chunk || '' === $chunk) {
                        break;
                    }
                    $head .= $chunk;
                }

                return $head;
            } finally {
                fclose($stream);
            }
        }

        return $this->fileDownloader->downloadHead($this->fileUrlResolver->resolveUrl($file), $length);
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
