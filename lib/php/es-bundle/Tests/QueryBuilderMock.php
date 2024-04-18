<?php

namespace Alchemy\ESBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class QueryBuilderMock extends QueryBuilder
{
    private $emCopy;
    private string $class;
    private array $ids;

    public function __construct(
        EntityManagerInterface $em,
        private readonly array $store
    ) {
        $this->emCopy = $em;
        parent::__construct($em);
    }

    public function from($from, $alias, $indexBy = null)
    {
        $this->class = $from;

        return parent::from($from, $alias, $indexBy);
    }

    public function setParameters($parameters)
    {
        if (isset($parameters['ids'])) {
            $this->ids = $parameters['ids'];
        }

        return parent::setParameters($parameters);
    }

    public function setParameter($key, $value, $type = null)
    {
        if ('ids' === $key) {
            $this->ids = $value;
        }

        return parent::setParameter($key, $value, $type);
    }

    public function getQuery()
    {
        $repo = $this->store[$this->class];
        $filtered = array_map(fn (string $id) => $repo[$id], $this->ids);

        return new QueryMock($this->emCopy, $filtered);
    }
}
