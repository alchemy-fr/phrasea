<?php

declare(strict_types=1);

namespace App\Service\Collection;

use App\Entity\Core\Collection;
use App\Http\LocaleContext;
use App\Util\NaturalSortKey;

/**
 * Sorts loaded collections in the order of the collection search (CollectionSearch::getDefaultSort()):
 * natural order of the displayed name, then creation date, then ID.
 */
final readonly class CollectionSorter
{
    public function __construct(
        private LocaleContext $localeContext,
    ) {
    }

    /**
     * @param Collection[] $collections
     *
     * @return list<Collection>
     */
    public function sort(array $collections): array
    {
        $prefs = [];
        $rows = [];
        foreach ($collections as $collection) {
            $workspace = $collection->getWorkspace();
            $locales = $prefs[$workspace->getId()] ??= $this->localeContext->getPreferredLocales($workspace);

            $rows[] = [
                'key' => NaturalSortKey::fromString(
                    $collection->getTranslatedField(Collection::TR_FIELD_NAME, $locales, $collection->getName()) ?? ''
                ),
                // Elasticsearch and the API output have a precision of one second
                'createdAt' => $collection->getCreatedAt()?->getTimestamp() ?? 0,
                'id' => (string) $collection->getId(),
                'collection' => $collection,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => NaturalSortKey::compare($a['key'], $b['key'])
            ?: $a['createdAt'] <=> $b['createdAt']
            ?: strcmp($a['id'], $b['id']) <=> 0);

        return array_map(static fn (array $row): Collection => $row['collection'], $rows);
    }
}
