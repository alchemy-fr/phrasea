<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\AssetManager;
use App\Storage\FileNameSanitizer;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fetches a file from a URL for a commit. A download holds its worker (which handles the notifications of the
 * targets too) until it ends: it may last app.download.max_duration seconds at most, however slow the server is.
 */
#[AsMessageHandler]
readonly class DownloadHandler
{
    private const string DEFAULT_FILE_NAME = 'download';

    public function __construct(
        private FileStorageManager $storageManager,
        #[AutowireInline(NoPrivateNetworkHttpClient::class, [new Reference('http_client')])]
        private HttpClientInterface $client,
        private AssetManager $assetManager,
        private MessageBusInterface $bus,
        private PathGeneratorInterface $pathGenerator,
        private EntityManagerInterface $em,
        private CommitValidator $commitValidator,
        private MultipartUploadPlanner $planner,
        private FileValidator $fileValidator,
        private LoggerInterface $logger,
        #[Autowire('%app.upload.max_file_size%')]
        private ?int $maxFileSize = null,
        #[Autowire('%app.download.max_duration%')]
        private float $maxDuration = 600,
    ) {
    }

    public function __invoke(Download $message): void
    {
        $url = $message->url;
        $startedAt = hrtime(true);

        try {
            $response = $this->client->request('GET', $url, [
                'max_duration' => $this->maxDuration,
            ]);
            $headers = $response->getHeaders();
        } catch (TransportExceptionInterface $e) {
            $this->abortIfTooLong($startedAt, $url, $e);

            throw $e;
        }

        $contentType = $headers['content-type'][0] ?? 'application/octet-stream';
        $contentType = trim(explode(';', $contentType, 2)[0]);

        // Header names are lower-cased by the HTTP client
        $originalName = self::getFileNameFromContentDisposition($headers['content-disposition'][0] ?? null)
            ?? self::getFileNameFromUrl($url);

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = !empty($extension) ? $extension : null;
        if (!$extension && 'application/octet-stream' !== $contentType) {
            $extension = new MimeTypes()->getExtensions($contentType)[0] ?? 'bin';
            $originalName = FileNameSanitizer::sanitize($originalName.'.'.$extension, self::DEFAULT_FILE_NAME);
        }

        $file = tmpfile();
        if (false === $file) {
            throw new \RuntimeException('Cannot create a temporary file for the download');
        }

        try {
            try {
                $size = $this->downloadTo($response, $file, $url);
            } catch (TransportExceptionInterface $e) {
                $this->abortIfTooLong($startedAt, $url, $e);

                throw $e;
            }

            try {
                $contentType = $this->fileValidator->validateFileContent(stream_get_meta_data($file)['uri'], $originalName, $contentType)->type;
            } catch (HttpExceptionInterface $e) {
                $this->abort($url, sprintf('Download refused: %s', $e->getMessage()), $e);
            }

            $path = $this->pathGenerator->generatePath($extension);
            rewind($file);
            $this->storageManager->storeStream($path, $file);
        } finally {
            // Closing the handle removes the temporary file
            fclose($file);
        }

        $target = DoctrineUtil::findStrict($this->em, Target::class, $message->targetId);

        $asset = $this->assetManager->createAsset(
            $target,
            $path,
            $contentType,
            $originalName,
            $size,
            $message->userId,
            $message->data
        );

        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setTotalSize($size);
        $commit->setLocale($message->locale);
        $commit->setFormData($message->formData ?? []);
        $commit->setUserId($message->userId);
        $commit->setFiles([$asset->getId()]);
        $commit->generateToken();

        try {
            $this->commitValidator->validate($commit);
        } catch (HttpExceptionInterface $e) {
            // The asset is removed with its file
            $this->em->remove($asset);
            $this->em->flush();

            $this->abort($url, sprintf('Download refused: %s', $e->getMessage()), $e);
        }

        $this->bus->dispatch($commit->toMessage());
    }

    /**
     * The name given by the server, when it gives one: `filename*` (RFC 6266) over `filename`.
     */
    public static function getFileNameFromContentDisposition(?string $contentDisposition): ?string
    {
        if (null === $contentDisposition) {
            return null;
        }

        if (preg_match('#(?:^|;)\s*filename\*\s*=\s*(UTF-8|ISO-8859-1)\'[^\']*\'([^;\s]+)#i', $contentDisposition, $regs)) {
            $name = rawurldecode($regs[2]);
            if ('UTF-8' !== strtoupper($regs[1])) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }
        } elseif (preg_match('#(?:^|;)\s*filename\s*=\s*"([^"]*)"#i', $contentDisposition, $regs)
            || preg_match('#(?:^|;)\s*filename\s*=\s*([^;\s"]+)#i', $contentDisposition, $regs)) {
            $name = $regs[1];
        } else {
            return null;
        }

        $name = FileNameSanitizer::sanitize($name, '');

        return '' !== $name ? $name : null;
    }

    /**
     * The last segment of the path of the URL.
     */
    public static function getFileNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return FileNameSanitizer::sanitize(is_string($path) ? rawurldecode($path) : '', self::DEFAULT_FILE_NAME);
    }

    /**
     * Streams the response body to $file, without holding it in memory.
     *
     * @param resource $file
     *
     * @return int the number of bytes written
     */
    private function downloadTo(ResponseInterface $response, $file, string $url): int
    {
        $maxSize = $this->maxFileSize ?? $this->planner->getMaxObjectSize();

        $contentLength = $response->getHeaders()['content-length'][0] ?? null;
        if (null !== $contentLength && is_numeric($contentLength) && (int) $contentLength > $maxSize) {
            $response->cancel();
            $this->abort($url, sprintf('Download refused: size %d exceeds the maximum of %d bytes', (int) $contentLength, $maxSize));
        }

        $size = 0;
        foreach ($this->client->stream($response) as $chunk) {
            $content = $chunk->getContent();
            $size += strlen($content);
            if ($size > $maxSize) {
                $response->cancel();
                $this->abort($url, sprintf('Download refused: the file exceeds the maximum of %d bytes', $maxSize));
            }

            if (false === fwrite($file, $content)) {
                throw new \RuntimeException('Cannot write the download to a temporary file');
            }
        }

        return $size;
    }

    /**
     * Not retried: a server that took max_duration once is likely to take it again.
     */
    private function abortIfTooLong(int|float $startedAt, string $url, TransportExceptionInterface $e): void
    {
        if ((hrtime(true) - $startedAt) / 1E9 >= $this->maxDuration) {
            $this->abort($url, sprintf('Download refused: it took more than %d seconds', (int) $this->maxDuration), $e);
        }
    }

    /**
     * The URL goes to the context of the log only: it comes from the user.
     */
    private function abort(string $url, string $reason, ?\Throwable $previous = null): never
    {
        $this->logger->error($reason, ['url' => $url]);

        throw new UnrecoverableMessageHandlingException($reason, 0, $previous);
    }
}
