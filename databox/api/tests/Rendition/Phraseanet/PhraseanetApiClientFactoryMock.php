<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use App\External\PhraseanetApiClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

class PhraseanetApiClientFactoryMock extends PhraseanetApiClientFactory
{
    private MockHandler $mock;
    private array $container = [];

    public function __construct()
    {
        $history = Middleware::history($this->container);
        $this->mock = new MockHandler();
        $handlerStack = HandlerStack::create($this->mock);
        $handlerStack->push($history);

        parent::__construct([
            'handler' => $handlerStack,
        ]);
    }

    public function shiftHistory(): array
    {
        if (empty($this->container)) {
            throw new \InvalidArgumentException('History is empty');
        }

        return array_shift($this->container);
    }

    public function getMock(): MockHandler
    {
        return $this->mock;
    }
}
