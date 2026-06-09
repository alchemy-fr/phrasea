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

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        dump($context);
        if (
            !is_object($data)
            || $data instanceof PaginatorInterface
        ) {
            return false;
        }

        return !isset($context[$this->getObjectKey($data)]);
    }

    public function normalize(mixed $data, $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        $context[$this->getObjectKey($data)] = true;
        $this->entityNormalizer->normalize($data, $context);

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => true,
            PaginatorInterface::class => false,
        ];
    }

    private function getObjectKey(object $object): string
    {
        return $object::class.self::ALREADY_CALLED;
    }
}
