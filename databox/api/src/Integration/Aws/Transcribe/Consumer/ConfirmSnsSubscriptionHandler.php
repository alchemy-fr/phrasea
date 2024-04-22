<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class ConfirmSnsSubscriptionHandler
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function __invoke(ConfirmSnsSubscription $message): void
    {
        $this->client->request('GET', $message->getUrl());
    }
}

