<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Upload;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pushes a local file to another Phrasea application through its multipart
 * upload API (storage-bundle "/uploads").
 *
 * The target API decides the part size and presigns every part when the
 * upload is created; the parts are then PUT straight to the storage. Expired
 * URLs are renewed with "/uploads/{id}/parts", transient failures are retried
 * and the upload is cancelled if a part cannot be sent.
 *
 * The returned payload is what the target API expects as "multipart" when
 * creating the resource (asset, rendition...).
 */
final readonly class MultipartUploader
{
    private const int MAX_ATTEMPTS = 3;

    /**
     * @param HttpClientInterface $storageClient client used for the presigned PUTs (no API credentials)
     */
    public function __construct(
        private HttpClientInterface $storageClient,
    ) {
    }

    /**
     * @param HttpClientInterface $apiClient client authenticated against the target API (base URI set)
     *
     * @return array{uploadId: string, parts: list<array{PartNumber: int, ETag: string}>}
     */
    public function upload(HttpClientInterface $apiClient, string $filePath, string $filename, ?string $type): array
    {
        $size = filesize($filePath);
        if (false === $size) {
            throw new \InvalidArgumentException(sprintf('Cannot read size of file "%s"', $filePath));
        }

        $upload = $apiClient->request('POST', '/uploads', [
            'json' => [
                'filename' => $filename,
                'type' => $type,
                'size' => $size,
            ],
        ])->toArray();

        $uploadId = $upload['id'];
        $chunkSize = (int) $upload['chunkSize'];
        if ($chunkSize < 1) {
            throw new \UnexpectedValueException(sprintf('Invalid part size %d returned for upload "%s"', $chunkSize, $uploadId));
        }
        /** @var array<int, string> $urls JSON keys "1", "2"... become integer keys */
        $urls = $upload['urls'] ?? [];
        // An empty file still needs one (empty) part
        $partCount = max(1, (int) ceil($size / $chunkSize));

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \InvalidArgumentException(sprintf('Cannot open file "%s"', $filePath));
        }

        try {
            $parts = [];
            for ($partNumber = 1; $partNumber <= $partCount; ++$partNumber) {
                $offset = ($partNumber - 1) * $chunkSize;
                $parts[] = [
                    'PartNumber' => $partNumber,
                    'ETag' => $this->putPart(
                        $apiClient,
                        $uploadId,
                        $urls,
                        $partNumber,
                        $handle,
                        $offset,
                        min($chunkSize, $size - $offset),
                    ),
                ];
            }
        } catch (\Throwable $e) {
            $this->cancel($apiClient, $uploadId);

            throw $e;
        } finally {
            fclose($handle);
        }

        return [
            'uploadId' => $uploadId,
            'parts' => $parts,
        ];
    }

    /**
     * @param array<int, string> $urls   presigned URLs, renewed in place when needed
     * @param resource           $handle
     */
    private function putPart(
        HttpClientInterface $apiClient,
        string $uploadId,
        array &$urls,
        int $partNumber,
        $handle,
        int $offset,
        int $length,
    ): string {
        $refreshed = false;
        if (!isset($urls[$partNumber])) {
            $urls = $this->fetchUrls($apiClient, $uploadId, $partNumber) + $urls;
            $refreshed = true;
        }

        $attempt = 0;
        while (true) {
            ++$attempt;
            fseek($handle, $offset);
            try {
                $headers = $this->storageClient->request('PUT', $urls[$partNumber] ?? throw new \UnexpectedValueException(sprintf('No upload URL for part %d', $partNumber)), [
                    'headers' => [
                        'Content-Length' => $length,
                    ],
                    'body' => self::createBodyReader($handle, $length),
                ])->getHeaders();

                return $headers['etag'][0] ?? throw new \UnexpectedValueException(sprintf('Missing ETag in the response of part %d', $partNumber));
            } catch (HttpExceptionInterface $e) {
                $status = $e->getResponse()->getStatusCode();
                if (403 === $status && !$refreshed) {
                    // The presigned URL expired during a long transfer: renew the remaining ones
                    $urls = $this->fetchUrls($apiClient, $uploadId, $partNumber) + $urls;
                    $refreshed = true;
                    --$attempt;

                    continue;
                }
                if ($status < 500 || $attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            } catch (TransportExceptionInterface $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param resource $handle
     */
    private static function createBodyReader($handle, int $length): \Closure
    {
        $read = 0;

        return static function (int $size) use ($handle, $length, &$read): string {
            $toRead = min($size, $length - $read);
            if ($toRead <= 0) {
                return '';
            }
            $data = fread($handle, $toRead);
            if (false === $data) {
                throw new \RuntimeException('Cannot read the file to upload');
            }
            $read += strlen($data);

            return $data;
        };
    }

    /**
     * @return array<int, string> part number => presigned URL, from $from to the last part
     */
    private function fetchUrls(HttpClientInterface $apiClient, string $uploadId, int $from): array
    {
        return $apiClient->request('POST', '/uploads/'.$uploadId.'/parts', [
            'json' => ['from' => $from],
        ])->toArray()['urls'];
    }

    private function cancel(HttpClientInterface $apiClient, string $uploadId): void
    {
        try {
            // Reading the status sends the request now and surfaces its errors here
            $apiClient->request('DELETE', '/uploads/'.$uploadId)->getStatusCode();
        } catch (\Throwable) {
            // The pending upload is pruned by the target anyway; keep the original error
        }
    }
}
