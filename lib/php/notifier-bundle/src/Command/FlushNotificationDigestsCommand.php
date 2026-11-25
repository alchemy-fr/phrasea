<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Command;

use Alchemy\NotifierBundle\Digest\DigestFlusher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Safety net for the delayed flush probes: sends every digest whose window has
 * elapsed. Meant for cron, and for environments where Messenger runs on a sync
 * transport (dev) so the delayed probes never fire.
 */
#[AsCommand(
    name: 'alchemy:notifier:digest:flush',
    description: 'Send the notification digests whose inactivity window has elapsed',
)]
final class FlushNotificationDigestsCommand extends Command
{
    public function __construct(
        private readonly DigestFlusher $flusher,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Flush every buffered digest even when its window has not elapsed yet', shortcut: 'f')]
        bool $force = false,
    ): int {
        $count = $this->flusher->flushOverdue($force);

        $io->success(sprintf('%d digest(s) flushed.', $count));

        return Command::SUCCESS;
    }
}
