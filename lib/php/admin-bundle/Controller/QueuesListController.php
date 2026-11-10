<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
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

            $queuesStatus[$queueName] = $status ?? [
                'queueName' => $queueName,
                'messageCount' => null,
                'consumerCount' => null,
            ];
        }

        return $this->render('@AlchemyAdmin/queues_list.html.twig', [
            'queuesStatus' => $queuesStatus,
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
