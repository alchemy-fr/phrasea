<?php

namespace Alchemy\MessengerBundle\Transport;

use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Keeps the AMQP connection active while a message is handled (`messenger:consume --keepalive`).
 *
 * ext-amqp only sends heartbeats during AMQP calls, so the connection stays silent for the whole
 * handling of a message: a long one (e.g. a remote file download) lets the network cut it as idle,
 * the ack then fails and RabbitMQ redelivers the message although it was handled.
 */
final class KeepaliveAmqpTransport extends AmqpTransport implements KeepaliveReceiverInterface
{
    public function __construct(
        private readonly Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        parent::__construct($connection, $serializer);
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        try {
            $channel = $this->connection->channel();
            if (!$channel->isConnected()) {
                throw new TransportException('The AMQP connection was lost while the message was being handled.');
            }

            // A basic.qos round trip: the receiver fetches with basic.get, which ignores the prefetch count.
            $channel->setPrefetchCount($channel->getPrefetchCount());
        } catch (\AMQPException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }
}
