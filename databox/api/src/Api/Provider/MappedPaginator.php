<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Lazily maps the items of a paginator.
 *
 * @template T
 *
 * @implements PaginatorInterface<T>
 * @implements \IteratorAggregate<T>
 */
final readonly class MappedPaginator implements PaginatorInterface, \IteratorAggregate
{
    /**
     * @param \Closure(mixed): T $mapper
     */
    public function __construct(
        private PaginatorInterface $paginator,
        private \Closure $mapper,
    ) {
    }

    public function getIterator(): \Generator
    {
        foreach ($this->paginator as $key => $item) {
            yield $key => ($this->mapper)($item);
        }
    }

    public function count(): int
    {
        return $this->paginator->count();
    }

    public function getLastPage(): float
    {
        return $this->paginator->getLastPage();
    }

    public function getTotalItems(): float
    {
        return $this->paginator->getTotalItems();
    }

    public function getCurrentPage(): float
    {
        return $this->paginator->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->paginator->getItemsPerPage();
    }
}
