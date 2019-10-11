<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    use ReloadDatabaseTrait;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param string|array|null $accessToken
     */
    protected function request(
        string $method,
        string $uri,
        $params = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): Response {
        $server['CONTENT_TYPE'] = $server['CONTENT_TYPE'] ?? 'application/json';
        $server['HTTP_ACCEPT'] = $server['HTTP_ACCEPT'] ?? 'application/json';

        if (empty($content) && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $content = !empty($params) ? json_encode($params) : 'null';
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

    protected function createPublication(): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $publication = new Publication();
        $publication->setLayout('gallery');
        $publication->setName('Foo');
        $em->persist($publication);
        $em->flush();

        return $publication->getId();
    }
}
