<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Hydra\Serializer\CollectionNormalizer;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class HydraMetaNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ApiMetaWrapperOutput;
    }

    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->normalizer->normalize($object->getResult(), $format, $context);

        if (CollectionNormalizer::FORMAT !== $format) {
            return $normalized;
        }

        return array_merge($normalized, $object->getMeta());
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ApiMetaWrapperOutput::class => true,
        ];
    }
}
