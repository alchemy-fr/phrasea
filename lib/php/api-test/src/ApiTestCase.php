<?php

declare(strict_types=1);

namespace Alchemy\ApiTest;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    public const UUID_REGEX = '[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}';

    protected ?AbstractBrowser $client = null;

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

        if ('PATCH' === $method) {
            $server['CONTENT_TYPE'] = 'application/merge-patch+json';
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        $em = self::getEntityManager();
        $em->close();
        $this->client = null;

        parent::tearDown();

        gc_collect_cycles();
    }

    protected function assertMatchesUuid($uuid): void
    {
        $this->assertMatchesRegularExpression('#^'.self::UUID_REGEX.'$#', $uuid);
    }

    /**
     * @template T
     *
     * @param class-string<T> $name
     *
     * @return T
     */
    protected static function getService(string $name): object
    {
        if (method_exists(self::class, 'getContainer')) {
            $container = static::getContainer();
        } else {
            $container = self::$container;
        }

        return $container->get($name);
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        return self::getService(EntityManagerInterface::class);
    }
}
