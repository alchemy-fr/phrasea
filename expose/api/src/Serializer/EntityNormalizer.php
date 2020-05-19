<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Serializer\Normalizer\EntityNormalizerInterface;

class EntityNormalizer
{
    /**
     * @var EntityNormalizerInterface[]
     */
    private array $normalizers = [];

    public function addNormalizer(EntityNormalizerInterface $normalizer): void
    {
        $this->normalizers[] = $normalizer;
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
