<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

class FacetRegistry
{
    /**
     * @var FacetInterface[]
     */
    private readonly array $facets;

    public function __construct(iterable $facets)
    {
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
