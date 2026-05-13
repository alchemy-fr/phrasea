<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\BuiltInField\BuiltInFieldInterface;
use App\Elasticsearch\BuiltInField\BuiltInFieldRegistry;
use App\Model\BuiltInField;
use Symfony\Contracts\Translation\TranslatorInterface;

class BuiltInFieldProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly BuiltInFieldRegistry $builtInFieldRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $results = array_map(function (BuiltInFieldInterface $field): BuiltInField {
            return new BuiltInField(
                $field::getName(),
                $field::getKey(),
                $this->translator->trans(sprintf('built_in_field.%s.name', $field::getName())),
                $field->getType(),
                $field->isMultiple(),
                $field->isFacet(),
                $field->isSortable(),
                $field->isSearchable(),
            );
        }, array_filter(iterator_to_array($this->builtInFieldRegistry->getAll()), fn (BuiltInFieldInterface $field): bool => $field->isListed()));

        usort($results, fn (BuiltInField $a, BuiltInField $b): int => $a->displayName <=> $b->displayName);

        return $results;
    }
}
