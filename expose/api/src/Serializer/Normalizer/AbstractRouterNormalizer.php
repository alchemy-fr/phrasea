<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Arthem\RequestSignerBundle\RequestSigner;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private string $storageBaseUrl;
    private RequestSigner $requestSigner;
    private RequestStack $requestStack;

    /**
     * @required
     */
    public function setRequestSigner(RequestSigner $requestSigner)
    {
        $this->requestSigner = $requestSigner;
    }

    /**
     * @required
     */
    public function setStorageBaseUrl(string $storageBaseUrl)
    {
        $this->storageBaseUrl = $storageBaseUrl;
    }

    /**
     * @required
     */
    public function setRequestStack(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->generateUrl($media->getPath(), $download);
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
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

        return $this->requestSigner->signUri(
                $this->storageBaseUrl.'/'.$path,
                $this->requestStack->getCurrentRequest(),
                $options
        );
    }
}
