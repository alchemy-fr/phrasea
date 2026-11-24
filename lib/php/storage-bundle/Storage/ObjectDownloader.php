<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

use Aws\CommandPool;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\PathPrefixer;
use Psr\Http\Message\StreamInterface;

/**
 * Downloads several objects of the main bucket at once, each one straight into a local file.
 */
final readonly class ObjectDownloader
{
    private PathPrefixer $prefixer;

    public function __construct(
        private S3Client $client,
        private string $bucketName,
        string $pathPrefix = '',
    ) {
        // The key under which the Flysystem adapter (FileStorageManager) writes the objects
        $this->prefixer = new PathPrefixer($pathPrefix);
    }

    /**
     * A failed download does not stop the others: its partial file is removed and its error returned.
     *
     * @param array<string, string> $objects storage path (as given to FileStorageManager) keyed by destination path
     *
     * @return array<string, \Throwable> the failures, keyed by destination path
     */
    public function downloadMany(array $objects, int $concurrency): array
    {
        if ([] === $objects) {
            return [];
        }

        $commands = (function () use ($objects): \Generator {
            foreach ($objects as $destination => $path) {
                yield $destination => $this->client->getCommand('GetObject', [
                    'Bucket' => $this->bucketName,
                    'Key' => $this->prefixer->prefixPath($path),
                    '@http' => ['sink' => $destination],
                ]);
            }
        })();

        $failures = [];
        new CommandPool($this->client, $commands, [
            'concurrency' => max(1, $concurrency),
            'preserve_iterator_keys' => true,
            'fulfilled' => static function (ResultInterface $result, string $destination) use (&$failures): void {
                try {
                    self::writeBody($result['Body'], $destination);
                } catch (\Throwable $e) {
                    @unlink($destination);
                    $failures[$destination] = $e;
                }
            },
            'rejected' => static function (\Throwable $reason, string $destination) use (&$failures): void {
                @unlink($destination);
                $failures[$destination] = $reason;
            },
        ])->promise()->wait();

        return $failures;
    }

    /**
     * The HTTP handler writes the body into the sink file; a handler ignoring the sink
     * (the one of the tests, keeping the objects in memory) leaves it to be copied.
     */
    private static function writeBody(StreamInterface $body, string $destination): void
    {
        try {
            if ($body->getMetadata('uri') === $destination) {
                return;
            }

            // Written aside then moved: the body may still be read from the destination file
            $tmp = $destination.'.part';
            try {
                if ($body->isSeekable()) {
                    $body->rewind();
                }
                $out = new LazyOpenStream($tmp, 'w');
                Utils::copyToStream($body, $out);
                $out->close();
                if (!rename($tmp, $destination)) {
                    throw new \RuntimeException(sprintf('Unable to write "%s"', $destination));
                }
            } finally {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        } finally {
            // The sink file stays open until the result is released: never hold one per download
            $body->close();
        }
    }
}
