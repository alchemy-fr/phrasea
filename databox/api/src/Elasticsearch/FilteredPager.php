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

        return array_filter($arr, $this->filter);
    }
}
