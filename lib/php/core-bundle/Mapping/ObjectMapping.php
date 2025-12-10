<?php

namespace Alchemy\CoreBundle\Mapping;

use Doctrine\Persistence\Proxy;

final readonly class ObjectMapping
{
    public function __construct(private array $map)
    {
    }

    public function isObjectMapped($object): bool
    {
        $className = self::getRealClass($object);

        if (false === array_search($className, $this->map, true)) {
            $reflection = new \ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== array_search($reflection->getName(), $this->map, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    public function getObjectTypes(): array
    {
        return array_keys($this->map);
    }

    private static function getRealClass($class): string
    {
        $class = is_string($class) ? $class : $class::class;

        if (false === $pos = strrpos($class, '\\'.Proxy::MARKER.'\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }

    public function getClassName(string|int $objectKey): string
    {
        if (!isset($this->map[$objectKey])) {
            throw new \InvalidArgumentException(sprintf('Undefined object "%s" in the object mapping', $objectKey));
        }

        return $this->map[$objectKey];
    }

    public function getObjectKey($object): string|int
    {
        $className = self::getRealClass($object);
        dump($className);

        if (false === $key = array_search($className, $this->map, true)) {
            $reflection = new \ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== $key = array_search($reflection->getName(), $this->map, true)) {
                    return $key;
                }
            }

            throw new \InvalidArgumentException(sprintf('Class "%s" is not defined in the object mapping', $className));
        }

        return $key;
    }
}
