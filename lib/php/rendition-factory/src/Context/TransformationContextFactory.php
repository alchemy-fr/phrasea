<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TransformationContextFactory
{
    private string $workingDirectory;

    public function __construct(
        private MimeTypeGuesser $mimeTypeGuesser,
        ?string $workingDirectory = null,
        private ?HttpClientInterface $client = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->workingDirectory = $workingDirectory ?? sys_get_temp_dir();
    }

    public function create(
        ?CreateRenditionOptions $options = null,
    ): TransformationContext {
        $baseDir = $options?->getWorkingDirectory() ?? $this->workingDirectory;
        $cacheDir = $options?->getCacheDirectory() ?? $baseDir.'/cache';
        $dateWorkingDir = $baseDir.'/'.date('Y-m-d');
        $workingDir = $dateWorkingDir.'/'.uniqid();

        foreach ([$baseDir, $cacheDir, $dateWorkingDir, $workingDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        return new TransformationContext(
            $workingDir,
            $cacheDir,
            $this->mimeTypeGuesser,
            $this->client ?? new NativeHttpClient(),
            $this->logger ?? new NullLogger(),
            $options->getMetadataContainer()
        );
    }
}
