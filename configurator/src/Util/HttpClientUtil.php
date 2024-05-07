<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\HttpClient\Exception\ClientException;

abstract class HttpClientUtil
{
    public static function debugError(callable $handler, ?int $ignoreHttpCode = null, ?array $data = null): void
    {
        try {
            $handler();
        } catch (ClientException $e) {
            if (null !== $ignoreHttpCode && $ignoreHttpCode === $e->getResponse()->getStatusCode()) {
                return;
            }

            $error = $e->getResponse()->getContent(false);

            throw new \InvalidArgumentException(sprintf('%s: %s%s', $e->getMessage(), $error, null !== $data ? ' (with data: '.print_r($data, true).')' : ''), 0, $e);
        }
    }
}
