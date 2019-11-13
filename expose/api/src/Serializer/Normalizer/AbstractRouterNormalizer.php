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

    protected function generateAssetUrl(string $route, PublicationAsset $publicationAsset): string
    {
        return $this->urlGenerator->generate($route, ['id' => $publicationAsset->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    protected function generateSubDefinitionUrl(string $route, PublicationAsset $publicationAsset, SubDefinition $subDefinition): string
    {
        return $this->urlGenerator->generate($route, [
            'id' => $publicationAsset->getId(),
            'type' => $subDefinition->getName(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
