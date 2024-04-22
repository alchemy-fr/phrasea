<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(Events::postFlush)]
final class PostFlushStack
{
    private array $callbacks = [];
    private array $messages = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly TerminateStackListener $terminateStackListener,
    ) {
    }

    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function addBusMessage(object $message): void
    {
        $this->messages[] = $message;
    }

    public function rollback(): void
    {
        $this->callbacks = [];
        $this->messages = [];
        $this->terminateStackListener->rollback();
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $callbacks = $this->callbacks;
        $this->callbacks = [];
        $messages = $this->messages;
        $this->messages = [];

        $em = $args->getObjectManager();
        if ($em->getConnection()->getTransactionNestingLevel() > 0) {
            while ($callback = array_shift($callbacks)) {
                $this->terminateStackListener->addCallback($callback);
            }

            while ($message = array_shift($messages)) {
                $this->terminateStackListener->addBusMessage($message);
            }

            return;
        }

        while ($callback = array_shift($callbacks)) {
            $callback();
        }

        while ($message = array_shift($messages)) {
            $this->bus->dispatch($message);
        }
    }
}
