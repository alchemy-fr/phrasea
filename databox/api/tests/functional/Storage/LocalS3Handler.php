<?php

declare(strict_types=1);

namespace App\Tests\Functional\Storage;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use League\Flysystem\FilesystemOperator;

/**
 * Terminal handler of the S3 client in the test environment: the object storage is not
 * reachable, the files are kept in the local test storage (see the "when@test" services).
 *
 * A multipart upload is expected to be sent already: completing it leaves the local file
 * as is, and HeadObject answers its real size.
 */
final readonly class LocalS3Handler
{
    public function __construct(
        private FilesystemOperator $storage,
        private string $pathPrefix,
    ) {
    }

    public static function install(S3Client $client, FilesystemOperator $storage, string $pathPrefix): S3Client
    {
        $client->getHandlerList()->setHandler(new self($storage, $pathPrefix));

        return $client;
    }

    public function __invoke(CommandInterface $command): PromiseInterface
    {
        $path = isset($command['Key']) ? $this->toLocalPath($command['Key']) : null;

        return match ($command->getName()) {
            'CreateMultipartUpload' => Create::promiseFor(new Result(['UploadId' => 'test-'.uniqid()])),
            'HeadObject' => null !== $path && $this->storage->fileExists($path)
                ? Create::promiseFor(new Result(['ContentLength' => $this->storage->fileSize($path)]))
                : Create::rejectionFor(new S3Exception('Not Found', $command, ['code' => 'NotFound'])),
            'DeleteObject' => $this->delete($path),
            'ListMultipartUploads' => Create::promiseFor(new Result(['Uploads' => []])),
            default => Create::promiseFor(new Result([])),
        };
    }

    private function delete(?string $path): PromiseInterface
    {
        if (null !== $path) {
            $this->storage->delete($path);
        }

        return Create::promiseFor(new Result([]));
    }

    private function toLocalPath(string $key): string
    {
        return '' !== $this->pathPrefix && str_starts_with($key, $this->pathPrefix)
            ? substr($key, strlen($this->pathPrefix))
            : $key;
    }
}
