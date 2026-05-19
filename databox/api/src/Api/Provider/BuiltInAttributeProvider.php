<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\BuiltInField\BuiltInAttributeInterface;
use App\Elasticsearch\BuiltInField\BuiltInAttributeRegistry;
use App\Model\BuiltInAttribute;
use Symfony\Contracts\Translation\TranslatorInterface;

class BuiltInAttributeProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly BuiltInAttributeRegistry $builtInAttributeRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $results = array_map(function (BuiltInAttributeInterface $field): BuiltInAttribute {
            return new BuiltInAttribute(
                $field::getKey(),
                $field::getName(),
                $this->translator->trans(sprintf('built_in_attribute.%s.name', $field::getName())),
                $field->getType(),
                $field->isMultiple(),
                $field->isFacet(),
                $field->isSortable(),
                $field->isSearchable(),
                $field->isEnabled(),
            );
        }, iterator_to_array($this->builtInAttributeRegistry->getAll()));

        usort($results, fn (BuiltInAttribute $a, BuiltInAttribute $b): int => $a->displayName <=> $b->displayName);

        return $results;
    }
}
