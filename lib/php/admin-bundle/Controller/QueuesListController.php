<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Utils\PendingMessagesSummary;
use Alchemy\AuthBundle\Security\JwtUser;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'alchemy_admin_')]
#[IsGranted(JwtUser::ROLE_TECH)]
class QueuesListController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'alchemy_admin_queues';

    private const int CONFIRM_TIMEOUT = 30;

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
        return $this->renderQueues();
    }

    #[Route(path: '/admin/queues/{queue}/inspect', name: 'queues_inspect', methods: ['POST'])]
    public function inspect(Request $request, string $queue): Response
    {
        if (!in_array($queue, $this->queues, true)) {
            throw $this->createNotFoundException(sprintf('Unknown queue "%s"', $queue));
        }

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Invalid CSRF token, the queue was not inspected. Please retry.');

            return $this->redirectToRoute('alchemy_admin_queues_list');
        }

        return $this->renderQueues($queue);
    }

    private function renderQueues(?string $inspectedQueue = null): Response
    {
        $connection = $this->connect();
        $channel = $connection->channel();
        $queuesStatus = [];
        $transportsSetUp = false;
        $pendingMessages = null;
        $inspectError = null;

        foreach ($this->queues as $queueName) {
            $status = $this->getQueueStatus($connection, $channel, $queueName);
            if (null === $status && !$transportsSetUp) {
                $this->setupTransports();
                $transportsSetUp = true;

                $connection->reconnect();
                $channel = $connection->channel();
                $status = $this->getQueueStatus($connection, $channel, $queueName);
            }

            if ($queueName === $inspectedQueue && null !== $status) {
                try {
                    $pendingMessages = $this->inspectQueue($connection, $queueName);
                } catch (AMQPExceptionInterface|\RuntimeException $e) {
                    $inspectError = $e->getMessage();
                }
            }

            $queuesStatus[$queueName] = $status ?? [
                'queueName' => $queueName,
                'messageCount' => null,
                'consumerCount' => null,
            ];
        }

        return $this->render('@AlchemyAdmin/queues_list.html.twig', [
            'queuesStatus' => $queuesStatus,
            'inspectLimit' => $this->inspectLimit,
            'inspectedQueue' => $inspectedQueue,
            'pendingMessages' => $pendingMessages,
            'inspectError' => $inspectError,
            'csrfTokenId' => self::CSRF_TOKEN_ID,
        ]);
    }

    private function connect(): AbstractConnection
    {
        $isSsl = in_array(strtolower(getenv('RABBITMQ_SSL') ?: ''), [
            '1', 'y', 'true', 'on',
        ], true);

        if ($isSsl) {
            return new AMQPSSLConnection(
                $this->rabbitmqConfig['host'],
                $this->rabbitmqConfig['port'],
                $this->rabbitmqConfig['user'],
                $this->rabbitmqConfig['password'],
                $this->rabbitmqConfig['vhost']
            );
        }

        return new AMQPStreamConnection(
            $this->rabbitmqConfig['host'],
            $this->rabbitmqConfig['port'],
            $this->rabbitmqConfig['user'],
            $this->rabbitmqConfig['password'],
            $this->rabbitmqConfig['vhost'],
        );
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
     * Reads the first pending messages, publishes a copy of each at the end of the queue, then
     * acknowledges the originals once the broker has confirmed the copies.
     *
     * Requeuing the originals instead would flag them as redelivered: the Messenger workers reject
     * such messages and send them for retry, which uses up their retries at each inspection.
     * The inspected messages lose their position in the queue; a failure between the confirmation
     * of the copies and the acknowledgement of the originals leaves both in the queue.
     */
    private function inspectQueue(AbstractConnection $connection, string $queueName): PendingMessagesSummary
    {
        $summary = new PendingMessagesSummary();
        $channel = $connection->channel();

        try {
            /** @var list<AMQPMessage> $messages */
            $messages = [];
            while (count($messages) < $this->inspectLimit) {
                $message = $channel->basic_get($queueName);
                if (null === $message) {
                    break;
                }

                $messages[] = $message;
                $type = $message->has('application_headers')
                    ? ($message->get('application_headers')->getNativeData()['type'] ?? null)
                    : null;

                $summary->add(is_string($type) ? $type : null, $message->getBody());
            }

            if ([] === $messages) {
                return $summary;
            }

            $nacked = false;
            $channel->set_nack_handler(static function () use (&$nacked): void {
                $nacked = true;
            });
            $channel->confirm_select();

            foreach ($messages as $message) {
                $channel->basic_publish(new AMQPMessage($message->getBody(), $message->get_properties()), '', $queueName);
            }
            $channel->wait_for_pending_acks(self::CONFIRM_TIMEOUT);

            if ($nacked) {
                throw new \RuntimeException(sprintf('The broker refused to republish messages of queue "%s": the inspected messages are requeued and may be duplicated', $queueName));
            }

            $channel->basic_ack(end($messages)->getDeliveryTag(), true);
        } finally {
            $channel->close();
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
