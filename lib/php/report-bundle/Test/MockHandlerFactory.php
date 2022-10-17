<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle\Test;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

abstract class MockHandlerFactory
{
    public static function create(): HandlerStack
    {
        return MockHandler::createWithMiddleware([
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
            new Response(200),
        ]);
    }
}
