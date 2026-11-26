<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TransformationContextFactory
{
    public const int DEFAULT_MAX_DOWNLOAD_SIZE = 5 * 1024 * 1024 * 1024;
    public const float DEFAULT_MAX_DOWNLOAD_DURATION = 900.0;

    private string $workingDirectory;

    public function __construct(
        private MimeTypeGuesser $mimeTypeGuesser,
        ?string $workingDirectory = null,
        private ?HttpClientInterface $client = null,
        private ?LoggerInterface $logger = null,
        /**
         * The remote files (download module, watermarks) come from the rendition definitions,
         * which are not trusted with the internal services or the cloud metadata.
         */
        private bool $allowPrivateNetwork = false,
        /**
         * Bounds of each remote file download, in bytes and seconds.
         */
        private int $maxDownloadSize = self::DEFAULT_MAX_DOWNLOAD_SIZE,
        private float $maxDownloadDuration = self::DEFAULT_MAX_DOWNLOAD_DURATION,
    ) {
        if ($maxDownloadSize < 1 || $maxDownloadDuration <= 0) {
            throw new \InvalidArgumentException('Invalid remote file download limits');
        }

        $this->workingDirectory = $workingDirectory ?? sys_get_temp_dir();
    }

    public function create(?CreateRenditionOptions $options = null): TransformationContextInterface
    {
        $baseDir = $options?->getWorkingDirectory() ?? $this->workingDirectory;
        $cacheDir = $options?->getCacheDirectory() ?? $baseDir.'/cache';
        $dateWorkingDir = $baseDir.'/'.date('Y-m-d');
        $workingDir = $dateWorkingDir.'/'.uniqid();

        foreach ([$cacheDir, $workingDir] as $dir) {
            // Several processes may create the same parents at once
            if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to create the "%s" directory', $dir));
            }
        }

        return new TransformationContext(
            $workingDir,
            $cacheDir,
            $this->mimeTypeGuesser,
            $this->getClient(),
            $this->logger ?? new NullLogger(),
            $options?->getMetadataContainer(),
            $options?->getInputFiles() ?? [],
            $this->maxDownloadSize,
            $this->maxDownloadDuration,
        );
    }

    public function createReadOnlyContext(?CreateRenditionOptions $options = null): TransformationContextInterface
    {
        return new ReadOnlyTransformationContext(
            $this->mimeTypeGuesser,
            $this->logger ?? new NullLogger(),
            $options?->getMetadataContainer(),
            $options?->getInputFiles() ?? [],
        );
    }

    private function getClient(): HttpClientInterface
    {
        $client = $this->client ?? new NativeHttpClient();

        return $this->allowPrivateNetwork ? $client : new NoPrivateNetworkHttpClient($client);
    }
}
