<?php

namespace App\Tests\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeChangeService;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\CodeAttributeType;
use App\Attribute\Type\CollectionPathAttributeType;
use App\Attribute\Type\ColorAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\DurationAttributeType;
use App\Attribute\Type\EntityAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\HtmlAttributeType;
use App\Attribute\Type\IpAttributeType;
use App\Attribute\Type\JsonAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\SizeAttributeType;
use App\Attribute\Type\TextareaAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Tests\AbstractDataboxTestCase;

class AttributeTypeChangeTest extends AbstractDataboxTestCase
{
    public function testAttributeTypeChange(): void
    {
        $attributeTypeRegistry = self::getService(AttributeTypeRegistry::class);
        $changer = self::getService(AttributeTypeChangeService::class);

        $allTypes = $attributeTypeRegistry->getTypes();

        $strictFormats = [
            BooleanAttributeType::NAME,
            IpAttributeType::NAME,
            CollectionPathAttributeType::NAME,
            ColorAttributeType::NAME,
            DateTimeAttributeType::NAME,
            DateAttributeType::NAME,
            GeoPointAttributeType::NAME,
            NumberAttributeType::NAME,
            DurationAttributeType::NAME,
            SizeAttributeType::NAME,
        ];

        $disallowedTranslations = [
            TextAttributeType::NAME => [
                ...$strictFormats,
            ],
            TextareaAttributeType::NAME => [
                ...$strictFormats,
                EntityAttributeType::NAME,
                KeywordAttributeType::NAME,
                TextAttributeType::NAME,
            ],
            CodeAttributeType::NAME => [
                ...$strictFormats,
                EntityAttributeType::NAME,
                KeywordAttributeType::NAME,
                TextAttributeType::NAME,
            ],
            HtmlAttributeType::NAME => [
                ...$strictFormats,
                EntityAttributeType::NAME,
                KeywordAttributeType::NAME,
                TextAttributeType::NAME,
            ],
            JsonAttributeType::NAME => [
                ...$strictFormats,
                EntityAttributeType::NAME,
                KeywordAttributeType::NAME,
                TextAttributeType::NAME,
            ],
            BooleanAttributeType::NAME => [
                BooleanAttributeType::NAME,
                IpAttributeType::NAME,
                CollectionPathAttributeType::NAME,
                ColorAttributeType::NAME,
                DateTimeAttributeType::NAME,
                DateAttributeType::NAME,
                GeoPointAttributeType::NAME,
                DurationAttributeType::NAME,
                SizeAttributeType::NAME,
            ],
            CollectionPathAttributeType::NAME => [
                ...$strictFormats,
            ],
            ColorAttributeType::NAME => [
                ...$strictFormats,
            ],
            DateAttributeType::NAME => array_diff($strictFormats, [
                DateTimeAttributeType::NAME,
            ]),
            DateTimeAttributeType::NAME => array_diff($strictFormats, [
                DateAttributeType::NAME,
            ]),
            EntityAttributeType::NAME => [
                ...$strictFormats,
            ],
            GeoPointAttributeType::NAME => [
                ...$strictFormats,
            ],
            IpAttributeType::NAME => [
                ...$strictFormats,
            ],
            NumberAttributeType::NAME => [
                ...$strictFormats,
            ],
            KeywordAttributeType::NAME => [
                ...$strictFormats,
            ],
            DurationAttributeType::NAME => [
                ...$strictFormats,
            ],
            SizeAttributeType::NAME => [
                ...$strictFormats,
            ],
        ];

        foreach ($allTypes as $t1) {
            foreach ($allTypes as $t2) {
                $t1Name = $t1->getName();
                $t2Name = $t2->getName();
                if ($t1Name === $t2Name) {
                    continue;
                }

                $canChangeType = $changer->canChangeType($t1Name, $t2Name);
                $message = sprintf('%s to %s', $t1Name, $t2Name);

                if (isset($disallowedTranslations[$t1Name]) && in_array($t2Name, $disallowedTranslations[$t1Name])) {
                    $this->assertFalse($canChangeType, $message);
                } else {
                    $this->assertTrue($canChangeType, $message);
                }
            }
        }
    }
}
