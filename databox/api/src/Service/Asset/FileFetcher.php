<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\FileValidator;
use App\Border\UriDownloader;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Service\Asset\Exception\PrivateFileException;
use App\Service\Asset\Exception\RefusedFileContentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The temporary copies created here (when no destination path is given) belong to this service:
 * they are shared between the callers of a same unit of work through File::$localTmpPath, and
 * deleted once it ends (message handled or failed, request or command terminated, reset).
 */
#[AsEventListener(KernelEvents::TERMINATE, 'reset', priority: -255)]
#[AsEventListener(ConsoleEvents::TERMINATE, 'reset', priority: -255)]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'reset', priority: -255)]
#[AsEventListener(WorkerMessageFailedEvent::class, method: 'reset', priority: -255)]
class FileFetcher implements ResetInterface
{
    /**
     * @var array<string, true>
     */
    private array $temporaryPaths = [];

    public function __construct(
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly UriDownloader $fileDownloader,
        private readonly FileStorageManager $fileStorageManager,
        private readonly FileValidator $fileValidator,
    ) {
    }

    public function __destruct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        foreach (array_keys($this->temporaryPaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }

    /**
     * Whether the file content can be retrieved by the platform: either stored on
     * the main storage, or a remote URL declared as publicly reachable.
     */
    public function isFetchable(File $file): bool
    {
        return File::STORAGE_S3_MAIN === $file->getStorage() || $file->isPathPublic();
    }

    /**
     * @throws RefusedFileContentException when the content downloaded from a URL is not the one the content check accepted
     */
    public function getFile(File $file, ?string $path = null): string
    {
        if (!$this->isFetchable($file)) {
            throw new PrivateFileException($file);
        }

        if (null === $path && $file->localTmpPath && file_exists($file->localTmpPath)) {
            return $file->localTmpPath;
        }

        $isTemporary = null === $path;

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $path ??= sys_get_temp_dir().'/'.uniqid('fetch-file');
            if ($isTemporary) {
                $this->temporaryPaths[$path] = true;
            }
            $stream = $this->fileStorageManager->getStream($file->getPath());
            file_put_contents($path, $stream);
            fclose($stream);

            return $file->localTmpPath = $path;
        }

        $path = $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), path: $path);
        if ($isTemporary) {
            $this->temporaryPaths[$path] = true;
        }

        $this->validateDownloadedContent($file, $path);

        return $file->localTmpPath = $path;
    }

    /**
     * The content check only reads the head of a remote file (getHead): the server
     * could answer each download with another content, so every full download is checked again.
     */
    private function validateDownloadedContent(File $file, string $path): void
    {
        if (FileAnalysisStateEnum::Bypassed === $file->getAnalysisState()) {
            // An admin accepted the file despite its refusal
            return;
        }

        try {
            $detectedType = $this->fileValidator->validateFileContent($path, $file->getFileName(), $file->getType());
            if (null !== $file->getDetectedType() && $detectedType !== $file->getDetectedType()) {
                throw new BadRequestHttpException(sprintf('Content is "%s", "%s" was accepted', $detectedType, $file->getDetectedType()));
            }
        } catch (BadRequestHttpException $e) {
            @unlink($path);
            unset($this->temporaryPaths[$path]);

            throw new RefusedFileContentException($file, $e->getMessage(), $e);
        }
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

        $path = $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), $headers);
        $this->temporaryPaths[$path] = true;

        return $file->localTmpPath = $path;
    }
}
