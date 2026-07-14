<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Serializer\Normalizer\EntityNormalizerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class EntityNormalizer
{
    /**
     * @param EntityNormalizerInterface[] $normalizers
     */
    public function __construct(
        #[TaggedIterator(EntityNormalizerInterface::TAG)]
        private iterable $normalizers,
    ) {
    }

    public function normalize(object $object, array &$context = []): void
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->support($object)) {
                $normalizer->normalize($object, $context);
            }
        }
    }
}
