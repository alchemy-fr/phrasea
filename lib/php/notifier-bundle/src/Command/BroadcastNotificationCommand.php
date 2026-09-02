<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Command;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Model\BroadcastOptions;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:notifier:broadcast',
    description: 'Send a topic notification to a whole audience (by default every user of the identity provider)',
)]
final class BroadcastNotificationCommand extends Command
{
    public function __construct(
        private readonly NotifierManager $notifier,
        private readonly UserDirectoryRegistry $directoryRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('topic', InputArgument::REQUIRED, 'The notification topic')
            ->addArgument('payload', InputArgument::OPTIONAL, 'JSON template parameters', '{}')
            ->addOption('channel', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, sprintf('Restrict delivery to those channels (%s)', implode(', ', ChannelType::values())))
            ->addOption('audience', 'a', InputOption::VALUE_REQUIRED, 'User directory to broadcast to')
            ->addOption('exclude-user', null, InputOption::VALUE_REQUIRED, 'userId to leave out of the broadcast')
            ->setHelp(<<<'HELP'
                  <info>%command.full_name% admin:message '{"subject":"Maintenance","body":"<p>Tonight</p>"}'</info>
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $topic = (string) $input->getArgument('topic');
        $params = json_decode((string) $input->getArgument('payload'), true, 512, JSON_THROW_ON_ERROR);

        /** @var array<int, string> $channels */
        $channels = $input->getOption('channel');
        $audience = $input->getOption('audience');

        // Fail here rather than in the worker
        foreach ($channels as $channel) {
            ChannelType::from($channel);
        }
        $directory = $this->directoryRegistry->get(null !== $audience ? (string) $audience : null);

        if (!$this->notifier->isEnabled()) {
            $io->warning('Notifications are globally disabled (NOTIFICATIONS_ENABLED), nothing was dispatched.');

            return Command::SUCCESS;
        }

        $this->notifier->broadcast($topic, $params, new BroadcastOptions(
            channels: [] !== $channels ? $channels : null,
            excludeUserId: $input->getOption('exclude-user'),
            directory: $directory->getName(),
        ));

        $io->success(sprintf('Broadcast of topic "%s" dispatched to "%s".', $topic, $directory->getLabel()));

        return Command::SUCCESS;
    }
}
