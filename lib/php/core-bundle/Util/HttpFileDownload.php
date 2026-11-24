<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The transfer of a response body into a file, chunk by chunk (see HttpDownloadUtil).
 *
 * @internal
 */
final class HttpFileDownload
{
    /**
     * @var resource|null
     */
    private $fileHandler;
    private bool $opened = false;
    private int $received = 0;

    public function __construct(
        public readonly string $path,
        public readonly ResponseInterface $response,
    ) {
    }

    /**
     * @return bool whether the download is complete
     *
     * @throws DownloadTooLargeException
     */
    public function write(ChunkInterface $chunk, int $maxSize): bool
    {
        // Throws on a transport error or a timeout
        $content = $chunk->getContent();
        if (null !== $chunk->getInformationalStatus()) {
            return false;
        }

        if (!$this->opened) {
            $this->open($maxSize);
        }

        $this->received += strlen($content);
        if ($this->received > $maxSize) {
            $this->response->cancel();

            throw new DownloadTooLargeException($this->getUri(), $maxSize);
        }
        if ('' !== $content) {
            fwrite($this->fileHandler, $content);
        }

        if (!$chunk->isLast()) {
            return false;
        }

        fclose($this->fileHandler);
        $this->fileHandler = null;

        return true;
    }

    /**
     * Cancels the transfer and removes the file, once opened.
     */
    public function fail(): void
    {
        $this->response->cancel();

        if ($this->opened) {
            if (is_resource($this->fileHandler)) {
                fclose($this->fileHandler);
            }
            $this->fileHandler = null;
            @unlink($this->path);
        }
    }

    private function open(int $maxSize): void
    {
        // Throws on an error status
        $declaredSize = $this->response->getHeaders()['content-length'][0] ?? null;
        if (is_numeric($declaredSize) && (int) $declaredSize > $maxSize) {
            $this->response->cancel();

            throw new DownloadTooLargeException($this->getUri(), $maxSize, (int) $declaredSize);
        }

        $fileHandler = fopen($this->path, 'w');
        if (false === $fileHandler) {
            throw new \RuntimeException(sprintf('Unable to open "%s" for writing', $this->path));
        }
        $this->fileHandler = $fileHandler;
        $this->opened = true;
    }

    /**
     * The query string of a signed URL is a credential: it is kept out of the messages.
     */
    private function getUri(): string
    {
        return UrlUtil::getUriWithoutQuery((string) $this->response->getInfo('url'));
    }
}
