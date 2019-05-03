<?php

declare(strict_types=1);

namespace App\Tests;

use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    use RefreshDatabaseTrait;

    const CLIENT_ID = 'mobile-app_12356789abcdefghijklmnopqrstuvwx';
    const CLIENT_SECRET = 'cli3nt_s3cr3t';

    /**
     * @var Client
     */
    protected $client;

    protected function request(string $method, string $uri, $params = [], array $files = []): Response
    {
        $this->client->request($method, $uri, $params, $files);

        /** @var Response $response */
        $response = $this->client->getResponse();

        return $response;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
    }
}
