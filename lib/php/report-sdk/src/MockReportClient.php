<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class MockReportClient implements ReportClientInterface
{
    private readonly ReportClient $client;
    private array $logs = [];

    public function __construct(
        string $appName,
        string $appId,
        LogValidator $logValidator = new LogValidator(),
        LoggerInterface $logger = new NullLogger(),
    ) {
        $this->client = new ReportClient($appName, $appId, new MockHttpClient(), $logValidator, $logger);
    }

    public function pushLog(
        string $action,
        ?string $userId = null,
        ?string $itemId = null,
        array $payload = [],
    ): void {
        $this->logs[] = [
            'action' => $action,
            'userId' => $userId,
            'itemId' => $itemId,
            'payload' => $payload,
        ];

        $this->client->pushLog($action, $userId, $itemId, $payload);
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
