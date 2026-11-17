<?php

namespace Alchemy\MessengerBundle\Transport;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[AsDecorator('messenger.transport.amqp.factory')]
final class KeepaliveAmqpTransportFactory extends AmqpTransportFactory
{
    public function createTransport(#[\SensitiveParameter] string $dsn, #[\SensitiveParameter] array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name']);

        return new KeepaliveAmqpTransport(Connection::fromDsn($dsn, $options), $serializer);
    }
}
