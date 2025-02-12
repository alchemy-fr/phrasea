<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Command;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'alchemy:notify:broadcast',
    description: 'Send notification to all users',
)]
class BroadcastNotificationCommand extends Command
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('notificationId', InputArgument::REQUIRED)
            ->addArgument('payload', InputArgument::OPTIONAL, 'JSON Payload', '{}')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifier->broadcast(
            $input->getArgument('notificationId'),
            json_decode($input->getArgument('payload') ?? '{}', true, 512, JSON_THROW_ON_ERROR)
        );

        return Command::SUCCESS;
    }
}
