<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Drops the items of a page that fail a predicate (typically a per-item security check
 * that cannot be expressed in the query). The page bounds and total are those of the
 * underlying paginator: the client may receive fewer items than the page size.
 *
 * @template T
 *
 * @implements PaginatorInterface<T>
 * @implements \IteratorAggregate<T>
 */
final class FilteredPaginator implements PaginatorInterface, \IteratorAggregate
{
    /** @var list<T>|null */
    private ?array $items = null;

    /**
     * @param \Closure(T): bool $predicate
     */
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly \Closure $predicate,
    ) {
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->getItems());
    }

    public function count(): int
    {
        return count($this->getItems());
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

    /**
     * @return list<T>
     */
    private function getItems(): array
    {
        return $this->items ??= array_values(array_filter(iterator_to_array($this->paginator, false), $this->predicate));
    }
}
