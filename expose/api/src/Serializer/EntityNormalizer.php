<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Serializer\Normalizer\EntityNormalizerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EntityNormalizer
{
    public function __construct(
        /**
         * @var EntityNormalizerInterface[]
         */
        #[AutowireIterator(EntityNormalizerInterface::TAG)]
        private iterable $normalizers,
    ) {
    }

    public function normalize($object, array &$context = []): void
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->support($object)) {
                $normalizer->normalize($object, $context);
            }
        }
    }
}
