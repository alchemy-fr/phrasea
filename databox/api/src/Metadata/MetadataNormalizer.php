<?php

namespace App\Metadata;

use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\Value\Binary;
use PHPExiftool\Driver\Value\Mono;
use PHPExiftool\Driver\Value\Multi;


class MetadataNormalizer
{
    /**
     * normalize metadata from metadataManipulator bundle (for File.metadata)
     *
     * @param MetadataBag $bag
     * @return array
     */
    public function normalizeToArray(MetadataBag $bag): array
    {
        $a = [];

        /** @var Metadata $meta */
        foreach ($bag as $meta) {
            $vMeta = $meta->getValue();
            if($vMeta instanceof Binary) {
                continue;
            }
            $a[$meta->getTagGroup()->getId()] = [
                'name'   => $meta->getTagGroup()->getName(),
                'value'  => $vMeta->asString(),
                'values' => $vMeta->asArray()
            ];
        }

        return $a;
    }

    public static function getBlankMeta(): array
    {
        return [
            'name'   => null,
            'value'  => null,
            'values' => null
        ];
    }
}
