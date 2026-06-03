<?php

declare(strict_types=1);

namespace App\Command;

use App\Matomo\MatomoClient;
use App\Matomo\PhraseanetClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @deprecated
 */
#[AsCommand(
    name: 'app:matomo:sync-phraseanet',
    description: 'Sync Matomo stats with Phraseanet records'
)]
final class SyncMatomoPhraseanetCommand extends Command
{
    public function __construct(
        private readonly MatomoClient $matomoClient,
        private readonly PhraseanetClient $phraseanetClient,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = 500;
        $offset = 0;
        while ($offset < 10000000) {
            $stats = $this->matomoClient->getStats($offset, $limit);

            foreach ($stats as $stat) {
                if (!is_array($stat)) {
                    $this->logger->error('Invalid stat format', ['stat' => $stat]);
                    continue;
                }
                $this->phraseanetClient->patchField($stat);
            }

            if (count($stats) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return 0;
    }
}
