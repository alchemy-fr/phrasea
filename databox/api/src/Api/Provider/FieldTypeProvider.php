<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Model\FieldType;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldTypeProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            $name = $uriVariables['name'] ?? null;

            foreach ($this->getFieldTypes() as $fieldType) {
                if ($fieldType->getName() === $name) {
                    return $fieldType;
                }
            }

            return null;
        }

        return parent::provide($operation, $uriVariables, $context);
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        return $this->getFieldTypes();
    }

    /**
     * @return FieldType[]
     */
    private function getFieldTypes(): array
    {
        $results = array_map(function (AttributeTypeInterface $type): FieldType {
            $t = new FieldType();
            $name = $type::getName();
            $t->setDisplayName($this->translator->trans(sprintf('field_type.types.%s', $name)));
            $t->setName($name);

            return $t;
        }, array_filter($this->attributeTypeRegistry->getTypes(), fn (AttributeTypeInterface $type): bool => $type->isListed()));

        usort($results, fn (FieldType $a, FieldType $b): int => $a->getDisplayName() <=> $b->getDisplayName());

        return $results;
    }
}
