<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use App\Border\UploaderOrigins;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploaderOriginsTest extends TestCase
{
    #[DataProvider('getUrls')]
    public function testOrigins(string $url, bool $isApi, bool $isFile): void
    {
        $origins = new UploaderOrigins('https://api-uploader.phrasea.local', 'https://minio.phrasea.local:8443');

        $this->assertSame($isApi, $origins->isUploaderApi($url));
        $this->assertSame($isFile, $origins->isUploaderFile($url));
    }

    public static function getUrls(): iterable
    {
        yield 'API' => ['https://api-uploader.phrasea.local/commits/42', true, true];
        yield 'API, explicit default port' => ['https://API-Uploader.phrasea.local:443/assets/42', true, true];
        yield 'storage' => ['https://minio.phrasea.local:8443/uploader/file.jpg?X-Amz-Signature=x', false, true];
        yield 'storage, other port' => ['https://minio.phrasea.local/uploader/file.jpg', false, false];
        yield 'other scheme' => ['http://api-uploader.phrasea.local/commits/42', false, false];
        yield 'other host' => ['https://api-uploader.phrasea.local.evil.com/commits/42', false, false];
        yield 'credentials before another host' => ['https://api-uploader.phrasea.local@169.254.169.254/latest', false, false];
        yield 'private network' => ['http://elasticsearch:9200/_search', false, false];
        yield 'not a URL' => ['/commits/42', false, false];
        yield 'unsupported scheme' => ['file:///etc/passwd', false, false];
    }

    public function testUnsetOriginsTrustNothing(): void
    {
        $origins = new UploaderOrigins(null, '');

        $this->assertFalse($origins->isUploaderApi('https://api-uploader.phrasea.local/commits/42'));
        $this->assertFalse($origins->isUploaderFile('https://minio.phrasea.local/file.jpg'));
    }
}
