<?php

declare(strict_types=1);

namespace App\Asset;

use App\Border\FileDownloader;
use App\Entity\Core\File;

class FileFetcher
{
    private FileUrlResolver $fileUrlResolver;
    private FileDownloader $fileDownloader;

    public function __construct(FileUrlResolver $fileUrlResolver, FileDownloader $fileDownloader)
    {
        $this->fileUrlResolver = $fileUrlResolver;
        $this->fileDownloader = $fileDownloader;
    }

    public function getFile(File $file, array &$headers = []): string
    {
        return $this->fileDownloader->download($this->fileUrlResolver->resolveUrl($file), $headers);
    }
}
