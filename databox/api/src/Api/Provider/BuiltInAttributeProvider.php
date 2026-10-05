<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeInterface;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Model\BuiltInAttribute;
use Symfony\Contracts\Translation\TranslatorInterface;

class BuiltInAttributeProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly BuiltInAttributeRegistry $builtInAttributeRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Built-in attributes are computed in memory, not stored: delegating an item
     * operation to the Doctrine item provider looks up a repository that does not
     * exist. Serve GET /built-in-attributes/{id} from the same list instead.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            $id = $uriVariables['id'] ?? null;

            foreach ($this->getBuiltInAttributes() as $attribute) {
                if ($attribute->id === $id) {
                    return $attribute;
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
        return $this->getBuiltInAttributes();
    }

    /**
     * @return BuiltInAttribute[]
     */
    private function getBuiltInAttributes(): array
    {
        $results = array_map(fn (BuiltInAttributeInterface $field): BuiltInAttribute => new BuiltInAttribute(
            $field::getKey(),
            $field::getName(),
            $this->translator->trans(sprintf('built_in_attribute.%s.name', $field::getName())),
            $field->getType(),
            $field->isMultiple(),
            $field->isFacet(),
            $field->isSortable(),
            $field->isSearchable(),
            $field->isEnabled(),
        ), iterator_to_array($this->builtInAttributeRegistry->getAll()));

        usort($results, fn (BuiltInAttribute $a, BuiltInAttribute $b): int => $a->displayName <=> $b->displayName);

        return $results;
    }
}
