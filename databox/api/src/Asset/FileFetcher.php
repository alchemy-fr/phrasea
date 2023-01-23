<?php

declare(strict_types=1);

namespace App\Asset;

use App\Border\UriDownloader;
use App\Entity\Core\File;
use InvalidArgumentException;

class FileFetcher
{
    private FileUrlResolver $fileUrlResolver;
    private UriDownloader $fileDownloader;

    public function __construct(FileUrlResolver $fileUrlResolver, UriDownloader $fileDownloader)
    {
        $this->fileUrlResolver = $fileUrlResolver;
        $this->fileDownloader = $fileDownloader;
    }

    public function getFile(File $file, array &$headers = []): string
    {
        if (!$file->isPathPublic()) {
            throw new InvalidArgumentException(sprintf('File "%s" has a private path', $file->getId()));
        }

        return $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), $headers);
    }
}
