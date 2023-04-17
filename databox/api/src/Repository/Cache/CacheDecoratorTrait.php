<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use Doctrine\ORM\Mapping\Entity;
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

    /**
     * @param Entity $entity
     * @return Entity
     */
    public function mergeEntity(Entity $entity): Entity
    {
        $this->getEntityManager()->merge($entity);

        return $entity;
    }

    /**
     * @param Entity[] $entities
     * @return Entity[]
     */
    public function mergeEntities(array $entities): array
    {
        foreach($entities as $entity) {
            $this->mergeEntity($entity);
        }
        return $entities;
    }
}
