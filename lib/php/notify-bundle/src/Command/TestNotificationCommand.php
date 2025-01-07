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
    name: 'alchemy:notify:send',
    description: 'Send notification to user',
)]
class TestNotificationCommand extends Command
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
            ->addArgument('userId', InputArgument::REQUIRED)
            ->addArgument('notificationId', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifier->notifyUser($input->getArgument('userId'), $input->getArgument('notificationId'));

        return Command::SUCCESS;
    }
}
