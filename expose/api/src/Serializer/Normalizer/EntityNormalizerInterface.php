<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(self::TAG)]
interface EntityNormalizerInterface
{
    final public const TAG = 'app.entity_normalizer';
    public function normalize($object, array &$context = []): void;

    public function support($object): bool;
}
