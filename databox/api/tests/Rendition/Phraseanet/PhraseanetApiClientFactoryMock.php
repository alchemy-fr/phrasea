<?php

declare(strict_types=1);

namespace App\Tests\Rendition\Phraseanet;

use App\External\PhraseanetApiClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Symfony\Component\HttpClient\MockHttpClient;

class PhraseanetApiClientFactoryMock extends PhraseanetApiClientFactory
{
    private readonly MockHttpClient $mockClient;

    public function __construct()
    {
        $this->mockClient = new MockHttpClient();

        parent::__construct($this->mockClient);
    }

    public function shiftHistory(): array
    {
        if (empty($this->container)) {
            throw new \InvalidArgumentException('History is empty');
        }

        return array_shift($this->container);
    }

    public function getMock(): MockHttpClient
    {
        return $this->mockClient;
    }
}
