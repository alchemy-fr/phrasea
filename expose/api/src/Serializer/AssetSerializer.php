<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Asset;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AssetSerializer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
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

    /**
     * @param Asset $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $object->setUrl($this->generateUrl('asset_open', $object));
        $object->setThumbUrl($this->generateUrl('asset_open', $object));
        $object->setDownloadUrl($this->generateUrl('asset_download', $object));

        return $this->decorated->normalize($object, $format, $context);
    }

    private function generateUrl(string $route, Asset $asset): string
    {
        return $this->urlGenerator->generate($route, ['id' => $asset->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Asset;
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
