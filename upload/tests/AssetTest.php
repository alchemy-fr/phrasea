<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class AssetTest extends WebTestCase
{
    /** @var Client */
    protected $client;

    public function testUploadAssetOK(): void
    {
        $response = $this->request('POST', '/assets', null, [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/ld+json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertRegExp('#^[a-z0-9]{2}/[0-9a-z]{2}/[a-z0-9\-]{36}\-jpg$#', $json['id']);
        $this->assertArrayHasKey('originalName', $json);
        $this->assertSame('32x32.jpg', $json['originalName']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $response = $this->request('POST', '/assets');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $response = $this->request('POST', '/assets', null, [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * @param string|array|null $content
     */
    protected function request(string $method, string $uri, $content = null, array $files = [], array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
        foreach ($headers as $key => $value) {
            if ('content-type' === strtolower($key)) {
                $server['CONTENT_TYPE'] = $value;

                continue;
            }

            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        if (is_array($content) && false !== preg_match('#^application/(?:.+\+)?json$#', $server['CONTENT_TYPE'])) {
            $content = json_encode($content);
        }

        $this->client->request($method, $uri, [], $files, $server, $content);

        return $this->client->getResponse();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
    }
}
