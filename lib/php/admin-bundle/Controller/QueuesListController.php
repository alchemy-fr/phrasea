<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
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

        foreach ($this->queues as $queueName) {
            try {
                [$queueName, $messageCount, $consumerCount] = $channel->queue_declare($queueName, true);
            } catch (\Exception $e) {
                $application = new Application($this->kernel);
                $application->setAutoExit(false);
                $input = new ArrayInput([
                    'command' => 'messenger:setup-transports',
                ]);
                $code = $application->run($input, new NullOutput());

                if (0 !== $code) {
                    throw new \RuntimeException(sprintf('Cannot setup transports, command exited with code %d', $code));
                }

                $connection->getConnection()->reconnect();
                $channel = $connection->channel();
                [$queueName, $messageCount, $consumerCount] = $channel->queue_declare($queueName, true);
            }

            $queuesStatus[$queueName] = [
                'queueName' => $queueName,
                'messageCount' => $messageCount,
                'consumerCount' => $consumerCount,
            ];
        }

        return $this->render('@AlchemyAdmin/queues_list.html.twig', [
            'queuesStatus' => $queuesStatus,
        ]);
    }
}
