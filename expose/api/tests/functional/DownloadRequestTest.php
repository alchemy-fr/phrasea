<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\DownloadRequest;

class DownloadRequestTest extends AbstractExposeTestCase
{
    public function testInvalidEmailIsRefused(): void
    {
        $id = $this->createArchivablePublication();

        foreach ([null, '', 'not-an-email', 'foo@', ['foo@example.com'], 42] as $email) {
            $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', null === $email ? [] : [
                'email' => $email,
            ]);
            $this->assertSame(400, $response->getStatusCode(), json_encode($email, JSON_THROW_ON_ERROR).': '.substr((string) $response->getContent(), 0, 300));
        }

        $this->assertSame(0, self::getEntityManager()->getRepository(DownloadRequest::class)->count([]));
    }

    public function testRequestsAreLimitedByClient(): void
    {
        $id = $this->createArchivablePublication();

        for ($i = 1; $i <= 10; ++$i) {
            $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
                'email' => sprintf('reader%d@example.com', $i),
            ]);
            $this->assertSame(200, $response->getStatusCode(), 'Request '.$i);
        }

        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'reader11@example.com',
        ]);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    public function testRequestsAreLimitedByRecipient(): void
    {
        $id = $this->createArchivablePublication();

        // From several clients
        foreach (['foo@example.com', 'Foo@example.com', 'FOO@EXAMPLE.COM', 'foo@Example.com', ' foo@example.com'] as $i => $email) {
            $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
                'email' => $email,
            ], [], ['REMOTE_ADDR' => '10.0.0.'.($i + 1)]);
            $this->assertSame(200, $response->getStatusCode(), $email);
        }

        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'fOo@example.com',
        ], [], ['REMOTE_ADDR' => '10.0.1.1']);
        $this->assertSame(429, $response->getStatusCode());

        $response = $this->request(null, 'POST', '/publications/'.$id.'/archive/download-request', [
            'email' => 'bar@example.com',
        ], [], ['REMOTE_ADDR' => '10.0.1.1']);
        $this->assertSame(200, $response->getStatusCode());
    }

    private function createArchivablePublication(): string
    {
        $publication = $this->createPublication(['downloadEnabled' => true]);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        return $publication->getId();
    }
}
