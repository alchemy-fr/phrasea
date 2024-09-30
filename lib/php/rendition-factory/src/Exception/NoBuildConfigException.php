<?php

namespace Alchemy\RenditionFactory\Exception;

class NoBuildConfigException extends \RuntimeException
{
    public static function throwNoFamily(string $family, string $mimeType): never
    {
        throw new self(sprintf('No build config defined for family "%s" (type: "%s")', $family, $mimeType));
    }

    public static function throwNoTransformation(string $family, string $mimeType): never
    {
        throw new self(sprintf('No transformation defined for family "%s" (type: "%s")', $family, $mimeType));
    }
}
