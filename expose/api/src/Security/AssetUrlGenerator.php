<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Arthem\RequestSignerBundle\RequestSigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AssetUrlGenerator
{
    private string $storageBaseUrl;
    private RequestSigner $requestSigner;
    private RequestStack $requestStack;
    private CloudFrontUrlGenerator $cloudFrontUrlGenerator;

    public function __construct(
        string $storageBaseUrl,
        RequestSigner $requestSigner,
        RequestStack $requestStack,
        CloudFrontUrlGenerator $cloudFrontUrlGenerator
    )
    {
        $this->storageBaseUrl = $storageBaseUrl;
        $this->requestSigner = $requestSigner;
        $this->requestStack = $requestStack;
        $this->cloudFrontUrlGenerator = $cloudFrontUrlGenerator;
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    public function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->generateUrl($media->getPath(), $download);
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    public function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->generateUrl($subDefinition->getPath(), $download);
    }

    private function generateUrl(string $path, bool $download): string
    {
        $options = [];
        if ($download) {
            $options['ResponseContentDisposition'] = sprintf(
                'attachment; filename=%s',
                basename($path)
            );
        }

        if ($this->cloudFrontUrlGenerator->isEnabled()) {
            return $this->cloudFrontUrlGenerator->getSignedUrl($path);
        }

        return $this->requestSigner->signUri(
            $this->storageBaseUrl.'/'.$path,
            $this->requestStack->getCurrentRequest() ?? Request::create('/'),
            $options
        );
    }
}
