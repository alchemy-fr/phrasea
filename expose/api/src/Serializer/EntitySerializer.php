<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\SubDefinition;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EntitySerializer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private $decorated;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(NormalizerInterface $decorated, UrlGeneratorInterface $urlGenerator)
    {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', DenormalizerInterface::class));
        }

        $this->decorated = $decorated;
        $this->urlGenerator = $urlGenerator;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        // TODO explode into entity serializers (one serializer per entity)
        if ($object instanceof Publication) {
            if ($object->getPackage() instanceof Asset) {
                $object->setPackageUrl($this->generateAssetUrl('asset_download', $object->getPackage()));
            }
            if ($object->getCover() instanceof Asset) {
                $object->setCoverUrl($this->generateAssetUrl('asset_thumbnail', $object->getCover()));
            }
        } elseif ($object instanceof Asset) {
            $object->setUrl($this->generateAssetUrl('asset_preview', $object));
            $object->setThumbUrl($this->generateAssetUrl('asset_thumbnail', $object));
            $object->setDownloadUrl($this->generateAssetUrl('asset_download', $object));
        } elseif ($object instanceof SubDefinition) {
            $object->setUrl($this->generateSubDefinitionUrl('asset_subdef_open', $object));
            $object->setDownloadUrl($this->generateSubDefinitionUrl('asset_subdef_download', $object));
        }

        return $this->decorated->normalize($object, $format, $context);
    }

    private function generateAssetUrl(string $route, Asset $asset): string
    {
        return $this->urlGenerator->generate($route, ['id' => $asset->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function generateSubDefinitionUrl(string $route, SubDefinition $subDefinition): string
    {
        return $this->urlGenerator->generate($route, [
            'id' => $subDefinition->getAsset()->getId(),
            'type' => $subDefinition->getName(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Asset
            || $data instanceof SubDefinition
            || $data instanceof Publication;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $class, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
