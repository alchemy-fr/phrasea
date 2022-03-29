<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\PaginatorInterface;
use Pagerfanta\Pagerfanta;

class PagerFantaApiPlatformPaginator implements PaginatorInterface, \IteratorAggregate
{
    private Pagerfanta $pagerfanta;

    public function __construct(Pagerfanta $pagerfanta)
    {
        $this->pagerfanta = $pagerfanta;
    }

    public function count()
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

    public function getIterator()
    {
        return $this->pagerfanta;
    }
}
