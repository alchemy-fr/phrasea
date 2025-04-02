<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class FacetRegistry
{
    /**
     * @var FacetInterface[]
     */
    private array $facets;

    public function __construct(
        #[TaggedIterator(tag: 'app.search.facet', defaultIndexMethod: 'getKey')]
        iterable $facets,
    ) {
        $this->facets = $facets instanceof \Traversable ? iterator_to_array($facets) : $facets;
    }

    public function getFacet(string $key): ?FacetInterface
    {
        return $this->facets[$key] ?? null;
    }

    public function getAll(): array
    {
        return $this->facets;
    }
}
