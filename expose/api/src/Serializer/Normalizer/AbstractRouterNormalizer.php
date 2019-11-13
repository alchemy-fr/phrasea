<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
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

    protected function generateAssetUrl(string $route, Asset $asset): string
    {
        return $this->urlGenerator->generate($route, ['id' => $asset->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    protected function generateSubDefinitionUrl(string $route, SubDefinition $subDefinition): string
    {
        return $this->urlGenerator->generate($route, [
            'id' => $subDefinition->getAsset()->getId(),
            'type' => $subDefinition->getName(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
