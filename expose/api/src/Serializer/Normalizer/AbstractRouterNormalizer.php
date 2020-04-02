<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Arthem\RequestSignerBundle\RequestSigner;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private UrlGeneratorInterface $urlGenerator;
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
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
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
    protected function generateAssetUrl(string $route, $publicationAsset): string
    {
        return $this->requestSigner->signUri(
            $this->urlGenerator->generate($route, ['id' => $publicationAsset->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->requestStack->getCurrentRequest()
        );
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateSubDefinitionUrl(string $route, $publicationAsset, SubDefinition $subDefinition): string
    {
        return $this->requestSigner->signUri(
            $this->urlGenerator->generate($route, [
                'id' => $publicationAsset->getId(),
                'type' => $subDefinition->getName(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->requestStack->getCurrentRequest()
        );
    }
}
