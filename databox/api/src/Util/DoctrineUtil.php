<?php

declare(strict_types=1);

namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineUtil
{
    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function findStrict(EntityManagerInterface $entityManager, string $class, string $id): object
    {
        $object = $entityManager->find($class, $id);

        return $object ?? throw new \InvalidArgumentException(sprintf('%s %s not found', $class, $id));
    }

    /**
     * @template T
     *
     * @param EntityRepository<T> $repo
     *
     * @return T
     */
    public static function findStrictByRepo(EntityRepository $repo, string $id): object
    {
        $object = $repo->find($id);

        return $object ?? throw new \InvalidArgumentException(sprintf('%s %s not found', $repo->getClassName(), $id));
    }
}
