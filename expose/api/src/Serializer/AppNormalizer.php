<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AppNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = self::class.'_ACD';

    public function __construct(
        private readonly EntityNormalizer $entityNormalizer,
    ) {
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        if (
            !is_object($data)
            || $data instanceof PaginatorInterface
        ) {
            return false;
        }

        return !isset($context[$this->getObjectKey($data)]);
    }

    public function normalize($object, $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        $context[$this->getObjectKey($object)] = true;
        $this->entityNormalizer->normalize($object, $context);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => true,
        ];
    }

    private function getObjectKey(object $object): string
    {
        return $object::class.self::ALREADY_CALLED;
    }
}
