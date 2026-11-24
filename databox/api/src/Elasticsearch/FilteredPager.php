<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Pagerfanta\Adapter\AdapterInterface;

final readonly class FilteredPager implements AdapterInterface
{
    public function __construct(private \Closure $filter, private AdapterInterface $decorated)
    {
    }

    public function getNbResults(): int
    {
        return $this->decorated->getNbResults();
    }

    public function getSlice($offset, $length): iterable
    {
        $arr = $this->decorated->getSlice($offset, $length);
        if (!is_array($arr)) {
            $arr = iterator_to_array($arr);
        }

        // A list: a page embedded in another resource (e.g. the children of a collection) is
        // normalized with its keys, and a gap would turn the JSON array into an object.
        return array_values(array_filter($arr, $this->filter));
    }
}
