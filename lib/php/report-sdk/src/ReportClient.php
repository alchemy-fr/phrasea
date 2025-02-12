<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReportClient implements ReportClientInterface
{
    private bool $reportIsDown = false;

    public function __construct(
        private readonly string $appName,
        private readonly string $appId,
        private readonly HttpClientInterface $client,
        private readonly LogValidator $logValidator = new LogValidator(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function pushLog(
        string $action,
        ?string $userId = null,
        ?string $itemId = null,
        array $payload = [],
    ): void {
        if ($this->reportIsDown) {
            return;
        }

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
            $this->client->request('POST', '/log', [
                'json' => $log,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->reportIsDown = true;
            $this->logger->alert(sprintf(
                'Unable to send log to report service: (%s) %s',
                $e->getMessage(),
                $e::class
            ));
        }
    }
}
