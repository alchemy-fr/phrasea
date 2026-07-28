<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

abstract class FilesystemUtils
{
    public static function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            if (false !== $objects) {
                foreach ($objects as $object) {
                    if ('.' !== $object && '..' !== $object) {
                        if (is_dir($dir.DIRECTORY_SEPARATOR.$object) && !is_link($dir.'/'.$object)) {
                            static::rrmdir($dir.DIRECTORY_SEPARATOR.$object);
                        } else {
                            unlink($dir.DIRECTORY_SEPARATOR.$object);
                        }
                    }
                }
                rmdir($dir);
            } else {
                throw new \RuntimeException(sprintf('The directory "%s" does not exist.', $dir));
            }
        }
    }
}
