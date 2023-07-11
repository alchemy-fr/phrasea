<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\HttpClient\Exception\ClientException;

abstract class HttpClientUtil
{
    public static function catchHttpCode(callable $handler, int $httpCode)
    {
        try {
            $handler();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== $httpCode) {
                throw $e;
            }
        }
    }
}
