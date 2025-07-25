<?php

declare(strict_types=1);

namespace App\Metadata;

use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\Value\Binary;

class MetadataNormalizer
{
    /**
     * normalize metadata from metadataManipulator bundle (for File.metadata).
     */
    public function normalize(MetadataBag $bag): array
    {
        $a = [];

        /** @var Metadata $meta */
        foreach ($bag as $meta) {
            $vMeta = $meta->getValue();

            // skip "declared-binary" and "binary-not-declared-binary" data
            if ($vMeta instanceof Binary) {
                continue;
            }
            try {
                if (!json_encode($vMeta->asString(), JSON_THROW_ON_ERROR)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $a[$meta->getTagGroup()->getId()] = [
                'name' => $meta->getTagGroup()->getName(),
                'values' => $vMeta->asArray(),
            ];
        }

        return $a;
    }
}
