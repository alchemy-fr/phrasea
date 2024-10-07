<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ReportClient
{
    private readonly LogValidator $logValidator;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly string $appName,
        private readonly string $appId,
        private readonly Client $client,
        ?LogValidator $logValidator = null,
        ?LoggerInterface $logger = null,
    ) {
        if (null === $logValidator) {
            $logValidator = new LogValidator();
        }
        $this->logValidator = $logValidator;
        $this->logger = $logger ?? new NullLogger();
    }

    public function pushLog(
        string $action,
        ?string $userId = null,
        ?string $itemId = null,
        array $payload = [],
    ): void {
        $log = [
            'action' => $action,
            'appName' => $this->appName,
            'appId' => $this->appId,
        ];
        if ($userId) {
            $log['user'] = $userId;
        }
        if ($itemId) {
            $log['item'] = $itemId;
        }
        if ($payload) {
            $log['payload'] = $payload;
        }

        $log['eventDate'] = time();

        $log = $this->logValidator->validate($log);

        try {
            $this->client->post('/log', [
                'json' => $log,
            ]);
        } catch (\Throwable $e) {
            $this->logger->alert(sprintf(
                'Unable to send log to report service: (%s) %s',
                $e->getMessage(),
                $e::class
            ));
        }
    }
}
