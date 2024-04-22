<?php

namespace Alchemy\MessengerBundle\Transport;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[AutoconfigureTag()]
class TestTransport implements TransportInterface
{
    private bool $intercept = false;

    public function __construct(
        private readonly SyncTransport $syncTransport,
        private readonly InMemoryTransport $inMemoryTransport,
    )
    {
    }

    public function get(): iterable
    {
        return $this->getTransport()->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->getTransport()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getTransport()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getTransport()->send($envelope);
    }

    private function getTransport(): TransportInterface
    {
        return $this->intercept ? $this->inMemoryTransport : $this->syncTransport;
    }

    public function intercept(bool $intercept = true): InMemoryTransport
    {
        $this->intercept = $intercept;

        return $this->inMemoryTransport;
    }

    public function getSyncTransport(): SyncTransport
    {
        return $this->syncTransport;
    }
}
