<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\MessageDeduplication;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The deduplication locks are held by the "in-memory" lock store of the test environment.
 */
final class MessageDeduplicationTest extends KernelTestCase
{
    public function testAnIdenticalMessageIsDroppedWhileOneIsQueued(): void
    {
        $transport = $this->interceptP1();
        $bus = $this->getBus();

        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));
        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));

        $this->assertSame(['ws-x'], $this->getQueuedWorkspaceIds($transport));
    }

    public function testMessagesWithDifferentPayloadsAreAllQueued(): void
    {
        $transport = $this->interceptP1();
        $bus = $this->getBus();

        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));
        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-y')));
        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));

        $this->assertSame(['ws-x', 'ws-y'], $this->getQueuedWorkspaceIds($transport));
    }

    public function testAMessageIsQueuedAgainOnceTheQueuedOneIsReceived(): void
    {
        $transport = $this->interceptP1();
        $bus = $this->getBus();

        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));
        $queued = iterator_to_array($transport->get(), false);
        $this->assertCount(1, $queued);

        // As a worker does
        $bus->dispatch($queued[0]->with(new ReceivedStamp('p1')));
        $transport->ack($queued[0]);
        $transport->reset();

        $bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets('ws-x')));

        $this->assertSame(['ws-x'], $this->getQueuedWorkspaceIds($transport));
    }

    private function interceptP1(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p1');

        return $transport->intercept();
    }

    private function getBus(): MessageBusInterface
    {
        return self::getContainer()->get(MessageBusInterface::class);
    }

    /**
     * @return list<string|null>
     */
    private function getQueuedWorkspaceIds(InMemoryTransport $transport): array
    {
        $ids = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof IndexAllAssets) {
                $ids[] = $message->workspaceId;
            }
        }

        return $ids;
    }
}
