<?php

namespace Alchemy\AclBundle\Mapping;

use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use ReflectionClass;

class ObjectMapping
{
    protected array $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function isObjectMapped($object): bool
    {
        $className = self::getRealClass($object);

        if (false === array_search($className, $this->mapping, true)) {
            $reflection = new ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== array_search($reflection->getName(), $this->mapping, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    public function getObjectTypes(): array
    {
        return array_keys($this->mapping);
    }

    private static function getRealClass($class): string
    {
        $class = is_string($class) ? $class : get_class($class);

        if (false === $pos = strrpos($class, '\\'.Proxy::MARKER.'\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }

    public function getClassName(string $objectKey): string
    {
        if (!isset($this->mapping[$objectKey])) {
            throw new InvalidArgumentException(sprintf('Undefined object "%s" in the object mapping', $objectKey));
        }

        return $this->mapping[$objectKey];
    }

    public function getObjectKey($object): string
    {
        $className = self::getRealClass($object);

        if (false === $key = array_search($className, $this->mapping, true)) {
            $reflection = new ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== $key = array_search($reflection->getName(), $this->mapping, true)) {
                    return $key;
                }
            }

            throw new InvalidArgumentException(sprintf('Class "%s" is not defined in the object mapping', $className));
        }

        return $key;
    }
}
