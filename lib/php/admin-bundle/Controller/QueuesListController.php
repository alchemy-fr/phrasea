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
    public function __construct()
    {
    }

    #[Route(path: '/admin/queues/list', name: 'queues_list')]
    public function __invoke(): Response
    {
        $Q = [
            'databox' => ['p1', 'p2'],
            'expose'  => ['p1'],
            'uploader'=> ['p1', 'p2', 'p3']
        ];

        $isSsl = in_array(strtolower(getenv('RABBITMQ_SSL') ?: ''), [
            '1', 'y', 'true', 'on',
        ], true);

        if ($isSsl) {
            $connection = new AMQPSSLConnection(
                getenv('RABBITMQ_HOST'),
                getenv('RABBITMQ_PORT'),
                getenv('RABBITMQ_USER'),
                getenv('RABBITMQ_PASSWORD'),
                getenv('RABBITMQ_VHOST')
            );
        } else {
            $connection = new AMQPStreamConnection(
                getenv('RABBITMQ_HOST'),
                getenv('RABBITMQ_PORT'),
                getenv('RABBITMQ_USER'),
                getenv('RABBITMQ_PASSWORD'),
                getenv('RABBITMQ_VHOST')
            );
        }

        $channel = $connection->channel();
        $queuesStatus = [];
        
        foreach ($Q[getenv('RABBITMQ_VHOST')] as $queueName) {
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
