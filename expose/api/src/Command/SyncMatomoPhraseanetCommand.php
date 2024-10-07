<?php

declare(strict_types=1);

namespace App\Command;

use App\Matomo\MatomoClient;
use App\Matomo\PhraseanetClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SyncMatomoPhraseanetCommand extends Command
{
    public static $defaultName = 'app:matomo:sync-phraseanet';

    private MatomoClient $matomoClient;
    private PhraseanetClient $phraseanetClient;

    public function __construct(
        MatomoClient $matomoClient,
        PhraseanetClient $phraseanetClient,
    ) {
        $this->matomoClient = $matomoClient;
        $this->phraseanetClient = $phraseanetClient;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = 500;
        $offset = 0;
        while ($offset < 10000000) {
            $stats = $this->matomoClient->getStats($offset, $limit);

            foreach ($stats as $stat) {
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
