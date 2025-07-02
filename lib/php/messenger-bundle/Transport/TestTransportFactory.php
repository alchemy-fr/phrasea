<?php

namespace Alchemy\MessengerBundle\Transport;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class TestTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.sync.factory')]
        private TransportFactoryInterface $syncTransportFactory,
        #[Autowire(service: 'messenger.transport.in_memory.factory')]
        private TransportFactoryInterface $inMemoryTransportFactory,
    ) {
    }

    public function createTransport(
        string $dsn,
        array $options,
        SerializerInterface $serializer,
    ): TransportInterface {
        return new TestTransport(
            $this->syncTransportFactory->createTransport($dsn, $options, $serializer),
            $this->inMemoryTransportFactory->createTransport($dsn, $options, $serializer),
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'test://');
    }
}
