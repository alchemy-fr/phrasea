<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DoctrineUtil
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
}
