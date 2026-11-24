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
        $failures = self::streamAllToFiles($client, [$path => static fn (): ResponseInterface => $response], 1, $maxSize);
        if (isset($failures[$path])) {
            throw $failures[$path];
        }
    }

    /**
     * Streams the bodies of several responses into their files, with the guarantees of streamToFile(),
     * at most $concurrency at once: a request is only sent once another one has ended.
     *
     * A failure only ends its own response: it is cancelled, its file removed and the error returned.
     *
     * @param array<string, \Closure(): ResponseInterface> $requests sending each request (the requests of $client), keyed by destination path
     *
     * @return array<string, \Throwable> the failures, keyed by destination path
     */
    public static function streamAllToFiles(
        HttpClientInterface $client,
        array $requests,
        int $concurrency,
        int $maxSize,
    ): array {
        $concurrency = max(1, $concurrency);
        $failures = [];
        /** @var array<int, HttpFileDownload> $active */
        $active = [];

        $fill = static function () use (&$requests, &$active, &$failures, $concurrency): void {
            while (count($active) < $concurrency && [] !== $requests) {
                $path = (string) array_key_first($requests);
                $send = $requests[$path];
                unset($requests[$path]);

                try {
                    $response = $send();
                } catch (\Throwable $e) {
                    $failures[$path] = $e;
                    continue;
                }
                $active[spl_object_id($response)] = new HttpFileDownload($path, $response);
            }
        };

        $fill();
        while ([] !== $active) {
            $responses = array_map(static fn (HttpFileDownload $download): ResponseInterface => $download->response, $active);
            $ended = null;
            foreach ($client->stream($responses) as $response => $chunk) {
                $download = $active[spl_object_id($response)];
                try {
                    if (!$download->write($chunk, $maxSize)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    $download->fail();
                    $failures[$download->path] = $e;
                }

                $ended = spl_object_id($response);
                // The set of the streamed responses is fixed: stream again with the next request
                break;
            }

            if (null === $ended) {
                foreach ($active as $download) {
                    $download->fail();
                    $failures[$download->path] = new \RuntimeException(sprintf('The response for "%s" ended without its last chunk', $download->path));
                }
                $active = [];
            } else {
                unset($active[$ended]);
            }

            $fill();
        }

        return $failures;
    }
}
