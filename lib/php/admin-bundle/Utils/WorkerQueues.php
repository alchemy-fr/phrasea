<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Utils;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Lists the RabbitMQ queues of the application: one per AMQP Messenger transport.
 *
 * The queue name is the transport name: each AMQP transport DSN ends with "/<transport name>",
 * which names the exchange, and Messenger names the queue after the exchange when the transport
 * has no "queues" option.
 *
 * The AMQP bridge is not a dependency of this bundle: when it is not installed, no transport
 * matches and the list is empty.
 */
final readonly class WorkerQueues
{
    public function __construct(
        #[AutowireLocator('messenger.receiver', indexAttribute: 'alias')]
        private ServiceProviderInterface $receivers,
    ) {
    }

    /**
     * @return list<string> in the transports declaration order
     */
    public function getNames(): array
    {
        $names = [];
        foreach (array_keys($this->receivers->getProvidedServices()) as $name) {
            if ($this->receivers->get($name) instanceof AmqpTransport) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
