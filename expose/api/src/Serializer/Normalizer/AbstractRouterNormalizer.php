<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @required
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateAssetUrl(string $route, $publicationAsset): string
    {
        return $this->urlGenerator->generate($route, ['id' => $publicationAsset->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateSubDefinitionUrl(string $route, $publicationAsset, SubDefinition $subDefinition): string
    {
        return $this->urlGenerator->generate($route, [
            'id' => $publicationAsset->getId(),
            'type' => $subDefinition->getName(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
