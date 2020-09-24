<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use Arthem\RequestSignerBundle\RequestSigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AssetNormalizer implements EntityNormalizerInterface
{
    private RequestSigner $requestSigner;
    private RequestStack $requestStack;
    private string $storageBaseUrl;

    public function __construct(
        RequestSigner $requestSigner,
        RequestStack $requestStack,
        string $storageBaseUrl
    ) {
        $this->requestSigner = $requestSigner;
        $this->requestStack = $requestStack;
        $this->storageBaseUrl = $storageBaseUrl;
    }

    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        $url = $this->requestSigner->signUri(
            $this->storageBaseUrl.'/'.$object->getPath(),
            $this->requestStack->getCurrentRequest() ?? Request::createFromGlobals(),
            []
        );
        $object->setUrl($url);
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
