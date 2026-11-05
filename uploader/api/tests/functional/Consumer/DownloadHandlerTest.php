<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\ApiTest\ApiTestCase;
use App\Consumer\Handler\Download;
use App\Consumer\Handler\DownloadHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Exception\TransportException;

class DownloadHandlerTest extends ApiTestCase
{
    #[DataProvider('privateUrlProvider')]
    public function testPrivateNetworkUrlsAreRefused(string $url): void
    {
        self::bootKernel();
        $handler = self::getContainer()->get(DownloadHandler::class);

        $this->expectException(TransportException::class);
        $handler(new Download($url, 'a_user_id', 'c705d014-5e18-4711-bad6-5e9e27e10099', 'en', null));
    }

    public static function privateUrlProvider(): array
    {
        return [
            ['http://169.254.169.254/latest/meta-data/'],
            ['http://127.0.0.1:9200/_search'],
            ['http://10.0.0.1/'],
            ['http://[::1]/'],
        ];
    }
}
