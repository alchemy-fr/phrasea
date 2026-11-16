<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class HttpDownloadUtil
{
    /**
     * Streams the body of a response into a file, refusing more than $maxSize bytes: the size the
     * server declares is checked first, then the bytes actually received (a server may lie or
     * send no Content-Length).
     *
     * The duration is bounded by the "max_duration" option of the request.
     * Whatever the failure once the file is opened, it is removed.
     *
     * @throws DownloadTooLargeException
     */
    public static function streamToFile(
        HttpClientInterface $client,
        ResponseInterface $response,
        string $path,
        int $maxSize,
    ): void {
        // The query string of a signed URL is a credential: it is kept out of the messages
        $uri = UrlUtil::getUriWithoutQuery((string) $response->getInfo('url'));

        $fileHandler = null;
        $opened = false;
        try {
            // Throws on an error status, as streaming the response would
            $declaredSize = $response->getHeaders()['content-length'][0] ?? null;
            if (is_numeric($declaredSize) && (int) $declaredSize > $maxSize) {
                $response->cancel();

                throw new DownloadTooLargeException($uri, $maxSize, (int) $declaredSize);
            }

            $fileHandler = fopen($path, 'w');
            if (false === $fileHandler) {
                throw new \RuntimeException(sprintf('Unable to open "%s" for writing', $path));
            }
            $opened = true;

            $received = 0;
            foreach ($client->stream($response) as $chunk) {
                $content = $chunk->getContent();
                $received += strlen($content);
                if ($received > $maxSize) {
                    $response->cancel();

                    throw new DownloadTooLargeException($uri, $maxSize);
                }
                fwrite($fileHandler, $content);
            }

            fclose($fileHandler);
            $fileHandler = null;
        } catch (\Throwable $e) {
            if ($opened) {
                if (is_resource($fileHandler)) {
                    fclose($fileHandler);
                }
                @unlink($path);
            }

            throw $e;
        }
    }
}
