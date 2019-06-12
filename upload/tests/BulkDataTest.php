<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class BulkDataTest extends ApiTestCase
{
    public function testBulkDataEditOK(): void
    {
        $response = $this->request('GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{}', $response->getContent());

        $response = $this->request('POST', '/bulk-data/edit', [
            'data' => [
              'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);

        $response = $this->request('GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
    }
}
