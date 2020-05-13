<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Serializer\ItemNormalizer;
use App\Serializer\Normalizer\EntityNormalizerInterface;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EntitySerializer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    private NormalizerInterface $decorated;

    /**
     * @var EntityNormalizerInterface[]
     */
    private array $normalizers = [];

    public function __construct(NormalizerInterface $decorated)
    {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', DenormalizerInterface::class));
        }

        $this->decorated = $decorated;
    }

    public function addNormalizer(EntityNormalizerInterface $normalizer): void
    {
        $this->normalizers[] = $normalizer;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->support($object, $format)) {
                $normalizer->normalize($object, $context);
            }
        }

        return $this->decorated->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        if (!$this->decorated->supportsNormalization($data, $format)) {
            return false;
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->support($data, $format)) {
                return true;
            }
        }

        return true;
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

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated->hasCacheableSupportsMethod();
    }
}
