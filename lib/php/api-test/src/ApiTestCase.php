<?php

declare(strict_types=1);

namespace Alchemy\ApiTest;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    use ApiTestTrait;

    protected ?AbstractBrowser $client = null;

    protected function request(
        string|array|null $accessToken,
        string $method,
        string $uri,
        ?array $params = null,
        array $files = [],
        array $server = [],
        ?string $content = null,
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

        $server['CONTENT_TYPE'] ??= 'application/json';
        $server['HTTP_ACCEPT'] ??= 'application/json';

        if (empty($content) && null !== $params && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $content = json_encode($params, JSON_THROW_ON_ERROR);
            $params = [];
        } elseif (null === $params) {
            $params = [];
        }

        $this->client->request($method, $uri, $params, $files, $server, $content);

        /** @var Response $response */
        $response = $this->client->getResponse();

        return $response;
    }

    protected function setUp(): void
    {
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
}
