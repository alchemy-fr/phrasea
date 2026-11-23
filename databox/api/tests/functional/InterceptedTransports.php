<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Several test transports kept in memory at once, read as a single one: the messages
 * a test looks at may go to different transports (e.g. p2 and heavy).
 *
 * A worker releases the deduplication lock of a message when it receives it. The messages
 * kept here are never received: forgetting them, or no longer intercepting, releases their
 * locks, otherwise an identical message dispatched later in the test would be dropped.
 */
final readonly class InterceptedTransports
{
    /**
     * @param InMemoryTransport[] $queues
     */
    private function __construct(private array $queues, private LockFactory $lockFactory)
    {
    }

    public static function intercept(ContainerInterface $container, string ...$names): self
    {
        $queues = [];
        foreach ($names as $name) {
            /** @var TestTransport $transport */
            $transport = $container->get('messenger.transport.'.$name);
            $queues[] = $transport->intercept();
        }

        return new self($queues, $container->get(LockFactory::class));
    }

    public static function stop(ContainerInterface $container, string ...$names): void
    {
        $queues = [];
        foreach ($names as $name) {
            /** @var TestTransport $transport */
            $transport = $container->get('messenger.transport.'.$name);
            $queues[] = $transport->intercept(false);
        }

        (new self($queues, $container->get(LockFactory::class)))->releaseDeduplicationLocks();
    }

    /**
     * @return Envelope[]
     */
    public function getSent(): array
    {
        return array_merge(...array_map(static fn (InMemoryTransport $queue): array => $queue->getSent(), $this->queues));
    }

    public function reset(): void
    {
        $this->releaseDeduplicationLocks();
        foreach ($this->queues as $queue) {
            $queue->reset();
        }
    }

    private function releaseDeduplicationLocks(): void
    {
        foreach ($this->getSent() as $envelope) {
            if ($stamp = $envelope->last(DeduplicateStamp::class)) {
                $this->lockFactory->createLockFromKey($stamp->getKey())->release();
            }
        }
    }
}
