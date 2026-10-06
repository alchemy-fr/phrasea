<?php

namespace Alchemy\ESBundle\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;

class QueryBuilderMock extends QueryBuilder
{
    private $emCopy;
    private string $class;
    private array $ids;

    public function __construct(
        EntityManagerInterface $em,
        private readonly array $store,
    ) {
        $this->emCopy = $em;
        parent::__construct($em);
    }

    public function from(string $from, string $alias, ?string $indexBy = null): static
    {
        $this->class = $from;

        return parent::from($from, $alias, $indexBy);
    }

    public function setParameters(ArrayCollection $parameters): static
    {
        $ids = $parameters->findFirst(static fn (int $i, Parameter $parameter): bool => 'ids' === $parameter->getName());
        if (null !== $ids) {
            $this->ids = $ids->getValue();
        }

        return parent::setParameters($parameters);
    }

    public function setParameter(string|int $key, mixed $value, ParameterType|ArrayParameterType|string|int|null $type = null): static
    {
        if ('ids' === $key) {
            $this->ids = $value;
        }

        return parent::setParameter($key, $value, $type);
    }

    public function getQuery(): Query
    {
        $repo = $this->store[$this->class];
        $filtered = array_map(fn (string $id) => $repo[$id], $this->ids);

        return new QueryMock($this->emCopy, $filtered);
    }
}
