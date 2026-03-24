<?php

namespace App\Tests\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeChangeService;
use App\Attribute\Type\ColorAttributeType;
use App\Attribute\Type\IpAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Tests\AbstractDataboxTestCase;

class AttributeTypeChangeTest extends AbstractDataboxTestCase
{
    public function testAttributeTypeChange(): void
    {
        $attributeTypeRegistry = self::getService(AttributeTypeRegistry::class);
        $changer = self::getService(AttributeTypeChangeService::class);

        $allTypes = $attributeTypeRegistry->getTypes();

        $disallowedTranslations = [
            TextAttributeType::NAME => [
                ColorAttributeType::NAME,
                IpAttributeType::NAME,
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
