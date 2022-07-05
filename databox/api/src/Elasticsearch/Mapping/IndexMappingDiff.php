<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Util\ArrayUtil;

class IndexMappingDiff
{
    public function shouldReindex(array $indexedMapping, array $newMapping): bool
    {
        // TODO support index settings

        if ($this->propertiesDiffer($indexedMapping['mappings']['properties'] ?? [], $newMapping['mappings']['properties'] ?? [])) {
            return true;
        }

        return false;
    }

    private function propertiesDiffer(array $current, array $new): bool
    {
        if ($current == $new) {
            return false;
        }

        foreach ($new as $attr => $newConfig) {
            if (!isset($current[$attr])) {
                return true;
            }

            $currentConfig = $current[$attr] ?? [];

            if (($newConfig['analyzer'] ?? null) !== ($currentConfig['analyzer'] ?? null)) {
                return true;
            }
            if (($newConfig['type'] ?? null) !== ($currentConfig['type'] ?? null)) {
                return true;
            }
            if (isset($newConfig['meta'])
                && $newConfig['meta']['attribute_id'] !== ($currentConfig['meta']['attribute_id'] ?? null)) {
                return true;
            }
            if (!ArrayUtil::arrayAreSame($newConfig['fields'] ?? [], $currentConfig['fields'] ?? [])) {
                return true;
            }

            if (isset($newConfig['properties'])) {
                if ($this->propertiesDiffer($currentConfig['properties'] ?? [], $newConfig['properties'])) {
                    return true;
                }
            }
        }

        // TODO check if Attribute is present.

        return false;
    }
}
