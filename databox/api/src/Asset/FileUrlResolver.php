<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Core\File;

class FileUrlResolver
{
    public function __construct(private readonly UrlSigner $urlSigner)
    {
    }

    public function resolveUrl(File $file): string
    {
        return match ($file->getStorage()) {
            File::STORAGE_S3_MAIN => $this->urlSigner->getSignedUrl($file->getPath()),
            File::STORAGE_URL => $file->getPath(),
            default => throw new \RuntimeException(sprintf('Unsupported storage "%s"', $file->getStorage())),
        };
    }
}
