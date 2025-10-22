<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Core\File;

final readonly class FileUrlResolver
{
    public function __construct(private UrlSigner $urlSigner)
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
