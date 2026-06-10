<?php

namespace Alchemy\CoreBundle\Util;

use Symfony\Component\String\Slugger\AsciiSlugger;

abstract class StringUtil
{
    public static function slugify(string $str): string
    {
        $slugger = new AsciiSlugger();

        return $slugger->slug($str)->toString();
    }
}
