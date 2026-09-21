<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Pagerfanta\Adapter\AdapterInterface;

/**
 * Turns each page of a decorated adapter into something else, keeping the
 * decorated total. The mapper receives the whole slice, so it can resolve the
 * page in one query, and may drop items it cannot map.
 */
final readonly class MappedPager implements AdapterInterface
{
    /**
     * @param \Closure(array): array $mapper
     */
    public function __construct(private \Closure $mapper, private AdapterInterface $decorated)
    {
    }

    public function getNbResults(): int
    {
        return $this->decorated->getNbResults();
    }

    public function getSlice($offset, $length): iterable
    {
        $items = $this->decorated->getSlice($offset, $length);
        if (!is_array($items)) {
            $items = iterator_to_array($items);
        }

        return ($this->mapper)($items);
    }
}
