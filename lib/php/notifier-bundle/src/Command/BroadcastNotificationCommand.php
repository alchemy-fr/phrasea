<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Command;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Model\BroadcastOptions;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:notifier:broadcast',
    description: 'Send a topic notification to a whole audience (by default every user of the identity provider)',
    help: <<<'HELP'
          <info>%command.full_name% admin:message '{"subject":"Maintenance","body":"<p>Tonight</p>"}'</info>
        HELP,
)]
final class BroadcastNotificationCommand extends Command
{
    public function __construct(
        private readonly NotifierManager $notifier,
        private readonly UserDirectoryRegistry $directoryRegistry,
    ) {
        parent::__construct();
    }

    /**
     * @param string[] $channels
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The notification topic')]
        string $topic,
        #[Argument('JSON template parameters')]
        string $payload = '{}',
        #[Option('Restrict delivery to those channels', name: 'channel', shortcut: 'c', suggestedValues: [ChannelType::class, 'values'])]
        array $channels = [],
        #[Option('User directory to broadcast to', shortcut: 'a')]
        ?string $audience = null,
        #[Option('userId to leave out of the broadcast')]
        ?string $excludeUser = null,
    ): int {
        $params = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        // Fail here rather than in the worker
        foreach ($channels as $channel) {
            ChannelType::from($channel);
        }
        $directory = $this->directoryRegistry->get($audience);

        if (!$this->notifier->isEnabled()) {
            $io->warning('Notifications are globally disabled (NOTIFICATIONS_ENABLED), nothing was dispatched.');

            return Command::SUCCESS;
        }

        $this->notifier->broadcast($topic, $params, new BroadcastOptions(
            channels: [] !== $channels ? $channels : null,
            excludeUserId: $excludeUser,
            directory: $directory->getName(),
        ));

        $io->success(sprintf('Broadcast of topic "%s" dispatched to "%s".', $topic, $directory->getLabel()));

        return Command::SUCCESS;
    }
}
