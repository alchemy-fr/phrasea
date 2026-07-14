<?php

declare(strict_types=1);

namespace Alchemy\ApiTest;

use Doctrine\ORM\EntityManagerInterface;

trait ApiTestTrait
{
    final public const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    protected function assertMatchesUuid($uuid): void
    {
        $this->assertMatchesRegularExpression('#^'.self::UUID_REGEX.'$#', $uuid);
    }

    /**
     * @template T
     *
     * @param class-string<T> $name
     *
     * @return T
     */
    protected static function getService(string $name): object
    {
        return static::getContainer()->get($name);
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        return self::getService(EntityManagerInterface::class);
    }

    /**
     * @phpstan-param class-string<T> $resourceClass
     *
     * @return T|null
     *
     * @phpstan-return T|null
     *
     * @template T of object
     */
    protected function findOneBy(string $resourceClass, array $criteria): ?object
    {
        $container = static::getContainer();

        if (
            (
                !$container->has('doctrine')
                || null === $objectManager = $container->get('doctrine')->getManagerForClass($resourceClass)
            )
            && (
                !$container->has('doctrine_mongodb')
                || null === $objectManager = $container->get('doctrine_mongodb')->getManagerForClass($resourceClass)
            )
        ) {
            throw new \RuntimeException(\sprintf('"%s" only supports classes managed by Doctrine ORM or Doctrine MongoDB ODM. Override this method to implement your own retrieval logic if you don\'t use those libraries.', __METHOD__));
        }

        return $objectManager->getRepository($resourceClass)->findOneBy($criteria);
    }
}
