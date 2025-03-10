<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface EntityNormalizerInterface
{
    final public const string TAG = 'app.entity_normalizer';

    public function normalize($object, array &$context = []): void;

    public function support($object): bool;
}
