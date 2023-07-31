<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Serializer\Normalizer\EntityNormalizerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EntityNormalizer
{
    /**
     * @var EntityNormalizerInterface[]
     */
    private iterable $normalizers;

    public function __construct(
        #[TaggedIterator(EntityNormalizerInterface::TAG)]
        iterable $normalizers
    )
    {
        $this->normalizers = $normalizers;
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
