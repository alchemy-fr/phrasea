<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK\Tests;

use Alchemy\ReportSDK\Exception\InvalidLogException;
use Alchemy\ReportSDK\ReportClient;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ReportClientTest extends TestCase
{
    public function testPushLogOK(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], 'true'),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $reportClient = new ReportClient('test-app', $client);
        $reportClient->pushLog('asset_view');

        $this->assertEquals(0, $mock->count());
    }

    /**
     * @dataProvider pushLogErrorData
     */
    public function testPushLogErrors(array $args): void
    {
        $mock = new MockHandler([]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $reportClient = new ReportClient('test-app', $client);

        $this->expectException(InvalidLogException::class);

        call_user_func_array([$reportClient, 'pushLog'], $args);
    }

    public function pushLogErrorData(): array
    {
        return [
            [['']],
            [['invalid-action-format']],
            [['09']],
            [['unsupported_action']],
        ];
    }
}
