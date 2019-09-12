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

    const ADMIN_USER = 'admin@alchemy.fr';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param string|array|null $accessToken
     */
    protected function request(
        $accessToken,
        string $method,
        string $uri,
        $params = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): Response {
        if (null !== $accessToken) {
            if (is_array($accessToken)) {
                [$authType, $accessToken] = $accessToken;
            } else {
                $authType = 'Bearer';
            }
            $server['HTTP_AUTHORIZATION'] = $authType.' '.$accessToken;
        }
        $server['CONTENT_TYPE'] = $server['CONTENT_TYPE'] ?? 'application/json';
        $server['HTTP_ACCEPT'] = $server['HTTP_ACCEPT'] ?? 'application/json';

        if (empty($content) && !empty($params) && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $content = json_encode($params);
        }

        $this->client->request($method, $uri, $params, $files, $server, $content);

        /** @var Response $response */
        $response = $this->client->getResponse();

        return $response;
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
}
