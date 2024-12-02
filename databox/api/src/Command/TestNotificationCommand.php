<?php

declare(strict_types=1);

namespace App\Command;

use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

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
            ->setName('app:notifier:debug')
            ->setDescription('Send notification to user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create a Notification that has to be sent
        // using the "email" channel
        $notification = (new Notification('New Invoice'))
            ->content('You got a new invoice for 15 EUR.');

        // The receiver of the Notification
        $recipient = new Recipient(
            'test@phrasea.local',
        );

        // Send the notification to the recipient
        $this->notifier->send($notification, $recipient);

        return Command::SUCCESS;
    }
}
