<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Command;

use Alchemy\CoreBundle\Notification\NovuNotification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\NotifierInterface;

#[AsCommand(
    name: 'app:notification:debug',
    description: 'Send notification to user',
)]
class TestNotificationCommand extends Command
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create a Notification that has to be sent
        // using the "email" channel
        $notification = (new NovuNotification('demo-comment-on-task'))
            ->content('You got a new invoice for 15 EUR.');

        // The receiver of the Notification
        $recipient = new NovuSubscriberRecipient(
            '4242',
            'John',
            'Doe',
            'test@phrasea.local',
        );

        // Send the notification to the recipient
        $this->notifier->send($notification, $recipient);

        return Command::SUCCESS;
    }
}
