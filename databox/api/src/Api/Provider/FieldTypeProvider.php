<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\FieldType;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldTypeProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $results = array_map(function (AttributeTypeInterface $type): FieldType {
            $t = new FieldType();
            $name = $type::getName();
            $t->setTitle($this->translator->trans(sprintf('field_type.types.%s', $name)));
            $t->setName($name);

            return $t;
        }, $this->attributeTypeRegistry->getTypes());

        usort($results, fn (FieldType $a, FieldType $b): int => $a->getTitle() <=> $b->getTitle());

        return $results;
    }
}
