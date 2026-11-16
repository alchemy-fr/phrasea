<?php

declare(strict_types=1);

namespace App\Util;

final class UrlPattern
{
    /**
     * An http(s) URL: the only scheme the API may hand out as a file URL,
     * redirect to or fetch.
     */
    public const string HTTP_URL = '#^https?://[^\s/\\\\]#i';

    /**
     * Any URI scheme (e.g. "indexer:///path/to/file.jpg"), except those that
     * run code when opened by a browser.
     */
    public const string NON_EXECUTABLE_URI = '/^(?!(?:javascript|vbscript|data):)[a-z][a-z0-9+.\-]*:\S/i';

    public static function isHttpUrl(string $url): bool
    {
        return 1 === preg_match(self::HTTP_URL, $url);
    }
}
