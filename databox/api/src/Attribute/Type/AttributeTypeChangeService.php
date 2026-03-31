<?php

namespace App\Attribute\Type;

use App\Entity\Core\AttributeDefinition;

readonly class AttributeTypeChangeService
{
    public function handleTypeChange(string $previousType, string $newType, AttributeDefinition $attributeDefinition): void
    {
        if (!$this->canChangeType($previousType, $newType)) {
            throw new \InvalidArgumentException(sprintf('Changing field type from %s to %s is not allowed', $previousType, $newType));
        }
    }

    public function canChangeType(string $previousType, string $newType): bool
    {
        $longText = [
            TextareaAttributeType::NAME,
            CodeAttributeType::NAME,
            HtmlAttributeType::NAME,
            JsonAttributeType::NAME,
        ];

        $text = [
            ...$longText,
            EntityAttributeType::NAME,
            TextAttributeType::NAME,
            KeywordAttributeType::NAME,
        ];

        $allowedTranslations = [
            TextAttributeType::NAME => [
                ...$text,
            ],
            TextareaAttributeType::NAME => [
                ...$longText,
            ],
            KeywordAttributeType::NAME => [
                ...$text,
            ],
            BooleanAttributeType::NAME => [
                ...$text,
                NumberAttributeType::NAME,
            ],
            EntityAttributeType::NAME => [
                ...$text,
            ],
            CodeAttributeType::NAME => [
                ...$longText,
            ],
            CollectionPathAttributeType::NAME => [
                ...$text,
            ],
            ColorAttributeType::NAME => [
                ...$text,
            ],
            DateAttributeType::NAME => [
                ...$text,
                DateTimeAttributeType::NAME,
            ],
            DateTimeAttributeType::NAME => [
                ...$text,
                DateAttributeType::NAME,
            ],
            GeoPointAttributeType::NAME => [
                ...$text,
            ],
            HtmlAttributeType::NAME => [
                ...$longText,
            ],
            JsonAttributeType::NAME => [
                ...$longText,
            ],
            IpAttributeType::NAME => [
                ...$text,
            ],
            NumberAttributeType::NAME => [
                ...$text,
            ],
            DurationAttributeType::NAME => [
                ...$text,
            ],
            SizeAttributeType::NAME => [
                ...$text,
            ],
        ];

        if (isset($allowedTranslations[$previousType])) {
            if (in_array($newType, $allowedTranslations[$previousType], true)) {
                return true;
            }
        }

        return false;
    }
}
