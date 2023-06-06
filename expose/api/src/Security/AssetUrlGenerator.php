<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\SubDefinition;

class AssetUrlGenerator
{
    private UrlSigner $urlSigner;

    public function __construct(UrlSigner $urlSigner)
    {
        $this->urlSigner = $urlSigner;
    }

    public function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->generateUrl($media->getPath(), $download);
    }

    public function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->generateUrl($subDefinition->getPath(), $download);
    }

    private function generateUrl(string $path, bool $download): string
    {
        return $this->urlSigner->getSignedUrl($path, [
            'download' => $download,
        ]);
    }
}
