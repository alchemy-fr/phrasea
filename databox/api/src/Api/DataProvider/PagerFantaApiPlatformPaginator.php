<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\PaginatorInterface;
use Pagerfanta\Pagerfanta;

class PagerFantaApiPlatformPaginator implements PaginatorInterface, \IteratorAggregate
{
    public function __construct(private readonly Pagerfanta $pagerfanta)
    {
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
        return $this->pagerfanta;
    }
}
