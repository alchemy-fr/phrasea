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
        $allowedTranslations = [
            TextAttributeType::NAME => [
                TextareaAttributeType::NAME,
                EntityAttributeType::NAME,
            ],
            KeywordAttributeType::NAME => [
                TextAttributeType::NAME,
                TextareaAttributeType::NAME,
                EntityAttributeType::NAME,
            ],
            BooleanAttributeType::NAME => [
                TextareaAttributeType::NAME,
                TextAttributeType::NAME,
                CodeAttributeType::NAME,
                EntityAttributeType::NAME,
            ],
            EntityAttributeType::NAME => [
                TextAttributeType::NAME,
                KeywordAttributeType::NAME,
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
