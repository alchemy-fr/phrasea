<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\FieldType;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldTypeDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(private readonly AttributeTypeRegistry $attributeTypeRegistry, private readonly TranslatorInterface $translator)
    {
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $results = array_map(function (AttributeTypeInterface $type): FieldType {
            $t = new FieldType();
            $name = $type::getName();
            $t->setTitle($this->translator->trans(sprintf('field_type.types.%s', $name)));
            $t->setName($name);

            return $t;
        }, $this->attributeTypeRegistry->getTypes());

        usort($results, fn(FieldType $a, FieldType $b): int => $a->getTitle() <=> $b->getTitle());

        return $results;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return FieldType::class === $resourceClass;
    }
}
