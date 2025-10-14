<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

abstract class AbstractLabelledBuiltInField extends AbstractBuiltInField
{
    public function normalizeBucket(array $bucket): ?array
    {
        $newKey = [
            'value' => $bucket['key'],
            'label' => $this->resolveLabel($bucket['key']),
        ];

        $item = $this->resolveItem($bucket['key']);
        if (null !== $item) {
            $newKey['item'] = $item;
        }

        $bucket['key'] = $newKey;

        return $bucket;
    }
}
