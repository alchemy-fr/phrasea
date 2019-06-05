<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    use ReloadDatabaseTrait;

    const CLIENT_ID = 'mobile-app_12356789abcdefghijklmnopqrstuvwx';
    const CLIENT_SECRET = 'cli3nt_s3cr3t';

    /**
     * @var Client
     */
    protected $client;

    protected function request(
        string $method,
        string $uri,
        $params = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
        ?string $accessToken = null
    ): Response
    {
        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }
        $server['CONTENT_TYPE'] = $server['CONTENT_TYPE'] ?? 'application/json';
        $server['HTTP_ACCEPT'] = $server['HTTP_ACCEPT'] ?? 'application/json';

        $this->client->request($method, $uri, $params, $files, $server, $content);

        /** @var Response $response */
        $response = $this->client->getResponse();

        return $response;
    }

    /**
     * @return string The access token
     */
    protected function authenticateUser(string $email, string $password): string
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => $email,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);

        return $json['access_token'];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        return self::$container->get(EntityManagerInterface::class);
    }

    protected function assertPasswordIsInvalid(string $email, string $password): void
    {
        $response = $this->requestToken($email, $password);
        $this->assertEquals(400, $response->getStatusCode());
    }

    protected function assertPasswordIsValid(string $email, string $password): void
    {
        $response = $this->requestToken($email, $password);
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function requestToken(string $email, string $password): Response
    {
        return $this->request('POST', '/oauth/v2/token', [
            'username' => $email,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
    }
}
