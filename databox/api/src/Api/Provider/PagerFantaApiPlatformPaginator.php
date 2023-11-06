<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Pagerfanta\Pagerfanta;

final class PagerFantaApiPlatformPaginator implements PaginatorInterface, \IteratorAggregate
{
    private $transformer;

    public function __construct(private readonly Pagerfanta $pagerfanta, callable $transformer = null)
    {
        $this->transformer = $transformer;
    }

    public function count(): int
    {
        return count($this->pagerfanta->getIterator()->getArrayCopy());
    }

    public function getLastPage(): float
    {
        return (float) $this->pagerfanta->getNbPages();
    }

    public function getTotalItems(): float
    {
        return $this->pagerfanta->getNbResults();
    }

    public function getCurrentPage(): float
    {
        return (float) $this->pagerfanta->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->pagerfanta->getMaxPerPage();
    }

    public function getIterator(): \Traversable
    {
        if (!$this->transformer) {
            return $this->pagerfanta;
        }

        return new \ArrayIterator(array_map($this->transformer, iterator_to_array($this->pagerfanta)));
    }
}
