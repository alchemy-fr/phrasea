<?php

namespace App\Tests\Attribute\Type;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;

class AttributeTypeRegistyTestFactory
{
    public static function create(): AttributeTypeRegistry
    {
        $iterator = [];
        foreach ([
            new TextAttributeType(),
            new NumberAttributeType(),
            new GeoPointAttributeType(),
            new KeywordAttributeType(),
            new DateTimeAttributeType(),
            new DateAttributeType(),
            new BooleanAttributeType(),
        ] as $service) {
            $iterator[$service::getName()] = $service;
        }

        return new AttributeTypeRegistry($iterator);
    }
}
