<?php

namespace Alchemy\CoreBundle\Util;

use Symfony\Component\HttpFoundation\Request;

final readonly class UrlUtil
{
    public static function removeQueryParamFromUrl(string $url, string $key): string
    {
        $params = self::extractUrlParameters($url);
        if (null === $params) {
            return $url;
        }

        $baseUrl = strtok($url, '?');
        unset($params[$key]);

        $query = http_build_query($params);
        if (empty($query)) {
            return $baseUrl;
        }

        return $baseUrl.'?'.$query;
    }

    public static function extractUrlParameters(string $url): ?array
    {
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);

            return $params;
        }

        return null;
    }

    public static function getCurrentUri(Request $request, bool $includeQueryString = true): string
    {
        if ($includeQueryString) {
            return $request->getSchemeAndHttpHost().$request->getRequestUri();
        }

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
    }
}
