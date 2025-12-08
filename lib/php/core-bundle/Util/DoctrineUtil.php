<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DoctrineUtil
{
    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function findStrict(
        EntityManagerInterface $entityManager,
        string $class,
        string $id,
        bool $throw404 = false,
    ): object {
        $object = $entityManager->find($class, $id);

        return $object ?? self::throwNotFound($class, $id, $throw404);
    }

    /**
     * @template T
     *
     * @param EntityRepository<T> $repo
     *
     * @return T
     */
    public static function findStrictByRepo(EntityRepository $repo, string $id, bool $throw404 = false): object
    {
        $object = $repo->find($id);

        return $object ?? self::throwNotFound($repo->getClassName(), $id, $throw404);
    }

    private static function throwNotFound(string $class, string $id, bool $throw404): never
    {
        $error = sprintf('%s %s not found', $class, $id);
        if ($throw404) {
            throw new NotFoundHttpException($error);
        }

        throw new \InvalidArgumentException($error);
    }

    public static function iterateIds(EntityRepository $repo, array $ids): iterable
    {
        return $repo->createQueryBuilder('o')
            ->select('o')
            ->where('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->toIterable();
    }

    public static function getIndexFromIds(
        EntityRepository $repo,
        array $ids,
    ): array {
        $results = $repo->findBy([
            'id' => array_filter($ids, fn ($v) => !empty($v)),
        ]);

        $index = [];
        foreach ($results as $entity) {
            $index[$entity->getId()] = $entity;
        }

        return $index;
    }
}
