<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QueuesListController extends AbstractController
{
    public function __construct(private array $queues, private array $rabbitmqConfig)
    {
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
            list($queueName, $messageCount, $consumerCount) = $channel->queue_declare($queueName, true);
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
