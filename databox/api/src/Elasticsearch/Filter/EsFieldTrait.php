<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use ApiPlatform\Metadata\Parameter;

trait EsFieldTrait
{
    protected static function getEsField(Parameter $parameter): string
    {
        return $parameter->getExtraProperties()[ElasticsearchFilterInterface::ES_FIELD]
            ?? $parameter->getProperty()
            ?? $parameter->getKey();
    }
}
