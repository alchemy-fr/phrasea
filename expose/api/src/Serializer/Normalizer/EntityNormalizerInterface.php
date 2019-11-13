<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

interface EntityNormalizerInterface
{
    public function normalize($object, array &$context = []);

    public function support($object, $format): bool;
}
