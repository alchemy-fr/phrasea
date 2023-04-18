<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use Doctrine\Persistence\ObjectRepository;

trait CacheDecoratorTrait
{
    protected ObjectRepository $decorated;

    public function createQueryBuilder($alias, $indexBy = null)
    {
        return $this->decorated->createQueryBuilder($alias, $indexBy);
    }

    public function createResultSetMappingBuilder($alias)
    {
        return $this->decorated->createResultSetMappingBuilder($alias);
    }

    public function find($id, $lockMode = null, $lockVersion = null)
    {
        return $this->decorated->find($id, $lockMode, $lockVersion);
    }

    public function findAll()
    {
        return $this->decorated->findAll();
    }

    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->decorated->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null)
    {
        return $this->decorated->findOneBy($criteria, $orderBy);
    }

    public function getClassName()
    {
        return $this->decorated->getClassName();
    }
}
