<?php

declare(strict_types=1);

namespace App\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractConsumer implements ConsumerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function execute(AMQPMessage $msg)
    {
        try {
            return $this->doExecute(json_decode($msg->getBody(), true));
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());

            return self::MSG_REJECT;
        }
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    abstract protected function doExecute(array $message): int;
}
