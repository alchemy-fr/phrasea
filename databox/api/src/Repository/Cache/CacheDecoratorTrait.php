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
     * @param Entity[] $entities
     * @return Entity[]
     */
    public function mergeEntities(array $entities): array
    {
        foreach($entities as $entity) {
            $this->getEntityManager()->merge($entity);      // does not prevent "entity that was not configured to cascade persist..."
            // $this->getEntityManager()->persist($entity);    // does the job but why ?
        }
        return $entities;
    }
}
