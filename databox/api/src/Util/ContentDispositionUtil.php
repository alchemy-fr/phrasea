<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ContentDispositionUtil
{
    private const string DEFAULT_NAME = 'file';

    /**
     * A Content-Disposition header value for any file name (quotes, non-ASCII,
     * path separators…): the name goes in "filename*", with an ASCII
     * "filename" fallback for the clients that ignore it.
     *
     * @param string $disposition HeaderUtils::DISPOSITION_ATTACHMENT or HeaderUtils::DISPOSITION_INLINE
     */
    public static function make(string $disposition, string $filename): string
    {
        $filename = trim(str_replace(['/', '\\'], '-', $filename));
        if ('' === $filename) {
            $filename = self::DEFAULT_NAME;
        }

        return HeaderUtils::makeDisposition($disposition, $filename, self::getAsciiFallback($filename));
    }

    private static function getAsciiFallback(string $filename): string
    {
        if (1 === preg_match('#^[\x20-\x7E]+$#', $filename) && !str_contains($filename, '%')) {
            return $filename;
        }

        $slugger = new AsciiSlugger();
        $extension = $slugger->slug(pathinfo($filename, PATHINFO_EXTENSION))->toString();
        $name = '' !== $extension ? pathinfo($filename, PATHINFO_FILENAME) : $filename;
        $name = $slugger->slug($name)->toString() ?: self::DEFAULT_NAME;

        return '' !== $extension ? $name.'.'.$extension : $name;
    }
}
