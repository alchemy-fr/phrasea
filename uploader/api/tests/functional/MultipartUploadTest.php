<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

/**
 * The server decides the part size and presigns every part when the upload
 * is created; "/parts" hands out fresh URLs from a given part (resume / expiry).
 */
class MultipartUploadTest extends AbstractUploaderTestCase
{
    private const SIZE = 45 * 1024 * 1024;

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];
    }

    public function testCreateUploadReturnsThePlanAndEveryPartUrl(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/uploads', [
            'headers' => $this->authHeaders(),
            'json' => [
                'filename' => 'big.jpg',
                'type' => 'image/jpeg',
                'size' => self::SIZE,
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $data = $response->toArray();

        $this->assertMatchesUuid($data['id']);
        $this->assertSame(self::SIZE, $data['size']);
        $this->assertFalse($data['complete']);
        $this->assertIsInt($data['chunkSize']);
        $this->assertGreaterThanOrEqual(5 * 1024 * 1024, $data['chunkSize'], 'S3 needs parts of at least 5MB');

        $partCount = (int) ceil(self::SIZE / $data['chunkSize']);
        $this->assertGreaterThan(1, $partCount, 'The fixture must span several parts');
        $this->assertCount($partCount, $data['urls']);
        $this->assertSame(range(1, $partCount), array_map('intval', array_keys($data['urls'])));
        foreach ($data['urls'] as $partNumber => $url) {
            $this->assertStringContainsString('partNumber='.$partNumber, $url);
            $this->assertStringContainsString('uploadId=', $url);
            $this->assertStringContainsString('X-Amz-Signature=', $url);
        }

        // Fresh URLs from a given part, to resume or after expiry
        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => 2],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->toArray();
        $this->assertSame($data['chunkSize'], $plan['chunkSize']);
        $this->assertSame($partCount, $plan['partCount']);
        $this->assertSame(range(2, $partCount), array_map('intval', array_keys($plan['urls'])));

        // Past the last part: nothing left to upload, not an error
        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => $partCount + 1],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->toArray()['urls']);

        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => 0],
        ]);
        $this->assertSame(400, $response->getStatusCode());

        // Legacy single-part endpoint still works
        $response = $client->request('POST', '/uploads/'.$data['id'].'/part', [
            'headers' => $this->authHeaders(),
            'json' => ['part' => 1],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('partNumber=1', $response->toArray()['url']);

        // Cancelling releases the S3 upload
        $response = $client->request('DELETE', '/uploads/'.$data['id'], [
            'headers' => $this->authHeaders(),
        ]);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCreateUploadRequiresAuthentication(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/uploads', [
            'json' => [
                'filename' => 'big.jpg',
                'type' => 'image/jpeg',
                'size' => self::SIZE,
            ],
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }
}
