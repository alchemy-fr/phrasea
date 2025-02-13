<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK\Tests;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Alchemy\ReportSDK\ReportClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class ReportClientTest extends TestCase
{
    public function testPushLogOK(): void
    {
        $client = new MockHttpClient([
            new JsonMockResponse(true),
        ]);

        $reportClient = new ReportClient('test-app', 'app-id', $client);
        $reportClient->pushLog('asset_view');

        $this->assertEquals(1, $client->getRequestsCount());
    }

    /**
     * @dataProvider pushLogErrorData
     */
    public function testPushLogErrors(array $args): void
    {
        $client = new MockHttpClient([]);

        $reportClient = new ReportClient('test-app', 'app-id', $client);

        $this->expectException(InvalidLogException::class);

        call_user_func_array($reportClient->pushLog(...), $args);
    }

    public function pushLogErrorData(): array
    {
        return [
            [['']],
            [['invalid-action-format']],
            [['09']],
        ];
    }
}
