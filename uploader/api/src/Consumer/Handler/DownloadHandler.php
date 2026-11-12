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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AsMessageHandler]
readonly class DownloadHandler
{
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
    ) {
    }

    public function __invoke(Download $message): void
    {
        $url = $message->url;
        $response = $this->client->request('GET', $message->url);
        $headers = $response->getHeaders();
        $contentType = $headers['content-type'][0] ?? 'application/octet-stream';
        $contentType = trim(explode(';', $contentType, 2)[0]);

        $originalName = $url;
        $originalName = explode('?', $originalName, 2)[0];
        if (1 === preg_match('#^[a-z]+://[^/]+/?$#', $originalName)) {
            $originalName = rtrim($originalName, '/');
            $originalName .= '/index';
        } else {
            $originalName = basename($originalName);
        }
        // Header names are lower-cased by the HTTP client
        if (isset($headers['content-disposition'][0])) {
            $contentDisposition = $headers['content-disposition'][0];
            if (preg_match('#\s+filename="(.+?)"#', $contentDisposition, $regs)) {
                $originalName = $regs[1];
            }
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = !empty($extension) ? $extension : null;
        if (!$extension && 'application/octet-stream' !== $contentType) {
            $mimes = new MimeTypes();
            $extension = $mimes->getExtensions($contentType)[0];
            $originalName .= '.'.$extension;
        }

        $file = tmpfile();
        if (false === $file) {
            throw new \RuntimeException('Cannot create a temporary file for the download');
        }

        try {
            $size = $this->downloadTo($response, $file, $url);

            try {
                $this->fileValidator->validateFileContent(stream_get_meta_data($file)['uri'], $originalName, $contentType);
            } catch (HttpExceptionInterface $e) {
                $this->abort(sprintf('Download of "%s" refused: %s', $url, $e->getMessage()), $e);
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

            $this->abort(sprintf('Download of "%s" refused: %s', $url, $e->getMessage()), $e);
        }

        $this->bus->dispatch($commit->toMessage());
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
            $this->abort(sprintf('Download of "%s" refused: size %d exceeds the maximum of %d bytes', $url, (int) $contentLength, $maxSize));
        }

        $size = 0;
        foreach ($this->client->stream($response) as $chunk) {
            $content = $chunk->getContent();
            $size += strlen($content);
            if ($size > $maxSize) {
                $response->cancel();
                $this->abort(sprintf('Download of "%s" refused: the file exceeds the maximum of %d bytes', $url, $maxSize));
            }

            if (false === fwrite($file, $content)) {
                throw new \RuntimeException(sprintf('Cannot write the download of "%s" to a temporary file', $url));
            }
        }

        return $size;
    }

    private function abort(string $reason, ?\Throwable $previous = null): never
    {
        $this->logger->error($reason);

        throw new UnrecoverableMessageHandlingException($reason, 0, $previous);
    }
}
