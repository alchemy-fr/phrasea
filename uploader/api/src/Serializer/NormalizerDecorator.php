<?php

declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class NormalizerDecorator implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        private DenormalizerInterface&NormalizerInterface $decorated,
        private EntityNormalizer $entityNormalizer,
    ) {
    }

    public function normalize(mixed $data, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->entityNormalizer->normalize($data, $context);

        return $this->decorated->normalize($data, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
