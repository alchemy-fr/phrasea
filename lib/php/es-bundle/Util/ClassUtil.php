<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Util;

final class ClassUtil
{
    private const string PROXY_MARKER = '\\__CG__\\';

    /**
     * The class of an entity, its Doctrine proxy class name if any being resolved
     * (native lazy objects are instances of the entity class itself).
     *
     * @param class-string $class
     *
     * @return class-string
     */
    public static function getRealClass(string $class): string
    {
        if (false === $pos = strrpos($class, self::PROXY_MARKER)) {
            return $class;
        }

        return substr($class, $pos + \strlen(self::PROXY_MARKER));
    }
}
