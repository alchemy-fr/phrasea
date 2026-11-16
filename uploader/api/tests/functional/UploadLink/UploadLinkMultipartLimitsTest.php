<?php

declare(strict_types=1);

namespace App\Tests\Functional\UploadLink;

use App\Entity\UploadLink;
use App\Exception\UploadLinkQuotaExceededException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The limits of the multipart uploads apply before anything is opened on the storage. The holders of a
 * link share them, whatever their browser session; each link starts with none consumed.
 */
class UploadLinkMultipartLimitsTest extends AbstractUploadLinkTestCase
{
    /**
     * @var array<string, array{env: mixed, server: mixed}>
     */
    private array $previousEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => ['env' => $env, 'server' => $server]) {
            if (null === $env) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        $this->previousEnv = [];

        parent::tearDown();
    }

    /**
     * Read by the services built after it (the kernel is rebooted between requests).
     */
    private function setEnv(string $name, string $value): void
    {
        $this->previousEnv[$name] ??= ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    private function createUpload(UploadLink $link, int $size = 1, ?string $session = null): ResponseInterface
    {
        return $this->linkRequest($link->getToken(), 'POST', '/uploads', [
            'filename' => 'file.jpg',
            'type' => 'image/jpeg',
            'size' => $size,
        ], $session ?? Uuid::v4()->toRfc4122());
    }

    public function testAnExhaustedLinkCannotStartAnUpload(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 1]);
        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $response = $this->createUpload($link);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringEndsWith(UploadLinkQuotaExceededException::CODE, $response->toArray(false)['type']);
    }

    public function testAFileLargerThanWhatRemainsOfTheLinkIsRefused(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxTotalSize' => self::FIXTURE_SIZE + 100]);
        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $response = $this->createUpload($link, 101);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringEndsWith(UploadLinkQuotaExceededException::CODE, $response->toArray(false)['type']);

        $response = $this->createUpload($link, 100);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
    }

    public function testTheUploadsInProgressOfALinkAreLimited(): void
    {
        $this->setEnv('MULTIPART_UPLOAD_MAX_PENDING', '2');
        $link = $this->createLink($this->createTarget());

        $first = $this->createUpload($link, session: $session = Uuid::v4()->toRfc4122());
        $this->assertSame(201, $first->getStatusCode(), $first->getContent(false));
        $this->assertSame(201, $this->createUpload($link)->getStatusCode());

        // Another browser session of the same link
        $response = $this->createUpload($link);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('Too many uploads in progress', $response->toArray(false)['detail']);

        // Another link has its own limit
        $this->assertSame(201, $this->createUpload($this->createLink($this->createTarget('other-target')))->getStatusCode());

        // A cancelled upload is no longer in progress
        $response = $this->linkRequest($link->getToken(), 'DELETE', '/uploads/'.$first->toArray()['id'], null, $session);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(201, $this->createUpload($link)->getStatusCode());
    }

    public function testTheUploadRequestsOfALinkAreRateLimited(): void
    {
        $this->setEnv('MULTIPART_UPLOAD_RATE_LIMIT', '3');
        $link = $this->createLink($this->createTarget());
        $session = Uuid::v4()->toRfc4122();

        $response = $this->createUpload($link, session: $session);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $uploadId = $response->toArray()['id'];
        $this->assertSame(201, $this->createUpload($link)->getStatusCode());

        // Asking part URLs counts as well
        $response = $this->linkRequest($link->getToken(), 'POST', '/uploads/'.$uploadId.'/parts', ['from' => 1], $session);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));

        foreach ([
            fn (): ResponseInterface => $this->createUpload($link),
            fn (): ResponseInterface => $this->linkRequest($link->getToken(), 'POST', '/uploads/'.$uploadId.'/parts', ['from' => 1], $session),
        ] as $request) {
            $response = $request();
            $this->assertSame(429, $response->getStatusCode());
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            $this->assertNotNull($retryAfter, 'The client is told when to retry');
            $this->assertGreaterThanOrEqual(1, (int) $retryAfter);
            $this->assertLessThanOrEqual(60, (int) $retryAfter);
        }
    }
}
