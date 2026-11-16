<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Utils\PendingMessagesSummary;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'alchemy_admin_')]
class QueuesListController extends AbstractController
{
    public function __construct(
        private array $queues,
        private array $rabbitmqConfig,
        private KernelInterface $kernel,
        #[Autowire(param: 'alchemy_admin.worker_inspect_limit')]
        private int $inspectLimit,
    ) {
    }

    #[Route(path: '/admin/queues/list', name: 'queues_list')]
    public function __invoke(): Response
    {
        $isSsl = in_array(strtolower(getenv('RABBITMQ_SSL') ?: ''), [
            '1', 'y', 'true', 'on',
        ], true);

        if ($isSsl) {
            $connection = new AMQPSSLConnection(
                $this->rabbitmqConfig['host'],
                $this->rabbitmqConfig['port'],
                $this->rabbitmqConfig['user'],
                $this->rabbitmqConfig['password'],
                $this->rabbitmqConfig['vhost']
            );
        } else {
            $connection = new AMQPStreamConnection(
                $this->rabbitmqConfig['host'],
                $this->rabbitmqConfig['port'],
                $this->rabbitmqConfig['user'],
                $this->rabbitmqConfig['password'],
                $this->rabbitmqConfig['vhost'],
            );
        }

        $channel = $connection->channel();
        $queuesStatus = [];
        $transportsSetUp = false;

        foreach ($this->queues as $queueName) {
            $status = $this->getQueueStatus($connection, $channel, $queueName);
            if (null === $status && !$transportsSetUp) {
                $this->setupTransports();
                $transportsSetUp = true;

                $connection->reconnect();
                $channel = $connection->channel();
                $status = $this->getQueueStatus($connection, $channel, $queueName);
            }

            if (null !== $status) {
                $status['pendingMessages'] = $status['messageCount'] > 0 ? $this->inspectQueue($connection, $queueName) : null;
            }

            $queuesStatus[$queueName] = $status ?? [
                'queueName' => $queueName,
                'messageCount' => null,
                'consumerCount' => null,
                'pendingMessages' => null,
            ];
        }

        return $this->render('@AlchemyAdmin/queues_list.html.twig', [
            'queuesStatus' => $queuesStatus,
            'inspectLimit' => $this->inspectLimit,
        ]);
    }

    /**
     * @return array{queueName: string, messageCount: int, consumerCount: int}|null null when the queue does not exist
     */
    private function getQueueStatus(AbstractConnection $connection, AMQPChannel &$channel, string $queueName): ?array
    {
        try {
            [, $messageCount, $consumerCount] = $channel->queue_declare($queueName, true);
        } catch (AMQPProtocolChannelException) {
            // The broker closes the channel when a passive declare fails
            $channel = $connection->channel();

            return null;
        }

        return [
            'queueName' => $queueName,
            'messageCount' => $messageCount,
            'consumerCount' => $consumerCount,
        ];
    }

    /**
     * Reads the first pending messages without acknowledging them, then requeues them all.
     * While being read, these messages are invisible to the consumers, and they come back flagged as redelivered.
     */
    private function inspectQueue(AbstractConnection $connection, string $queueName): PendingMessagesSummary
    {
        $summary = new PendingMessagesSummary();
        $channel = $connection->channel();
        $lastDeliveryTag = null;

        try {
            while ($summary->getCount() < $this->inspectLimit) {
                $message = $channel->basic_get($queueName, false);
                if (null === $message) {
                    break;
                }

                $lastDeliveryTag = $message->getDeliveryTag();
                $type = $message->has('application_headers')
                    ? ($message->get('application_headers')->getNativeData()['type'] ?? null)
                    : null;

                $summary->add(is_string($type) ? $type : null, $message->getBody());
            }
        } finally {
            try {
                if (null !== $lastDeliveryTag) {
                    $channel->basic_nack($lastDeliveryTag, true, true);
                }
            } finally {
                $channel->close();
            }
        }

        return $summary;
    }

    private function setupTransports(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput([
            'command' => 'messenger:setup-transports',
        ]), new NullOutput());

        if (0 !== $code) {
            throw new \RuntimeException(sprintf('Cannot setup transports, command exited with code %d', $code));
        }
    }
}
