<?php

declare(strict_types=1);

namespace App\Asset;

use App\Entity\Core\File;
use App\Storage\UrlSigner;
use RuntimeException;

class FileUrlResolver
{
    private UrlSigner $urlSigner;

    public function __construct(UrlSigner $urlSigner)
    {
        $this->urlSigner = $urlSigner;
    }

    public function resolveUrl(File $file): string
    {
        switch ($file->getStorage()) {
            case File::STORAGE_S3_MAIN:
                return $this->urlSigner->getSignedUrl($file->getPath());
            case File::STORAGE_PUBLIC_URL:
                return $file->getPath();
            default:
                throw new RuntimeException(sprintf('Unsupported storage "%s"', $file->getStorage()));
        }
    }
}
