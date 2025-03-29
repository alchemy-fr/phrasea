<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use Symfony\Component\HttpFoundation\Response;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QueuesListController extends AbstractController
{
    public function __construct(private array $queueConfig, private array $rabbitConfig)
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
                $this->rabbitConfig['host'],
                $this->rabbitConfig['port'],
                $this->rabbitConfig['user'],
                $this->rabbitConfig['password'],
                $this->rabbitConfig['vhost']
            );
        } else {
            $connection = new AMQPStreamConnection(
                $this->rabbitConfig['host'],
                $this->rabbitConfig['port'],
                $this->rabbitConfig['user'],
                $this->rabbitConfig['password'],
                $this->rabbitConfig['vhost'],
            );
        }

        $channel = $connection->channel();
        $queuesStatus = [];
        
        foreach ($this->queueConfig as $queueName) {
            list($queueName, $messageCount, $consumerCount) = $channel->queue_declare($queueName, true);
            $queuesStatus[$queueName] = [
                'queueName'     => $queueName,
                'messageCount'  => $messageCount,
                'consumerCount' => $consumerCount
            ];
        }
        
        return $this->render('@AlchemyAdmin/queues_list.html.twig', [
            'queuesStatus' => $queuesStatus
        ]);
    }
}
