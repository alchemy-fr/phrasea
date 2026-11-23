<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Several test transports kept in memory at once, read as a single one: the messages
 * a test looks at may go to different transports (e.g. p2 and heavy).
 */
final readonly class InterceptedTransports
{
    /**
     * @param InMemoryTransport[] $queues
     */
    private function __construct(private array $queues)
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

        return new self($queues);
    }

    public static function stop(ContainerInterface $container, string ...$names): void
    {
        foreach ($names as $name) {
            /** @var TestTransport $transport */
            $transport = $container->get('messenger.transport.'.$name);
            $transport->intercept(false);
        }
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
        foreach ($this->queues as $queue) {
            $queue->reset();
        }
    }
}
