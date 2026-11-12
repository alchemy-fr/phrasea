<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\MediaInterface;
use App\Entity\SubDefinition;

readonly class AssetUrlGenerator
{
    public function __construct(private UrlSigner $urlSigner)
    {
    }

    public function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->generateUrl($media->getPath(), $media->getMimeType(), $download);
    }

    public function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->generateUrl($subDefinition->getPath(), $subDefinition->getMimeType(), $download);
    }

    /**
     * Without a known type, the file is always served as an opaque attachment.
     */
    public function generatePathUrl(string $path, bool $download = false): string
    {
        return $this->generateUrl($path, null, $download);
    }

    private function generateUrl(string $path, ?string $type, bool $download): string
    {
        return $this->urlSigner->getSignedUrl($path, [
            'type' => $type,
            'inline' => !$download,
            'download' => $download,
        ]);
    }
}
