<?php

declare(strict_types=1);

namespace Alchemy\ReportSDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ReportClient
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var LogValidator
     */
    private $logValidator;

    /**
     * @var string
     */
    private $appName;

    public function __construct(string $appName, Client $client, ?LogValidator $logValidator = null)
    {
        $this->client = $client;

        if (null === $logValidator) {
            $logValidator = new LogValidator();
        }
        $this->logValidator = $logValidator;
        $this->appName = $appName;
    }

    public function pushLog(string $action, ?string $userId = null, ?string $itemId = null, array $payload = []): void
    {
        $log = [
            'action' => $action,
            'app' => $this->appName,
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

        $log = $this->logValidator->validate($log);

        try {
            $this->client->post('/log', [
                'json' => $log,
            ]);
        } catch (ClientException $e) {
            throw $e;
        }
    }
}
