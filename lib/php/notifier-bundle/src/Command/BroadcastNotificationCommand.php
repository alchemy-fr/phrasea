<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Command;

use Alchemy\NotifierBundle\Manager\NotifierManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:notifier:broadcast',
    description: 'Send a topic notification to all subscribers',
)]
final class BroadcastNotificationCommand extends Command
{
    public function __construct(
        private readonly NotifierManager $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('topic', InputArgument::REQUIRED, 'The notification topic')
            ->addArgument('payload', InputArgument::OPTIONAL, 'JSON template parameters', '{}')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $topic = (string) $input->getArgument('topic');
        $params = json_decode((string) $input->getArgument('payload'), true, 512, JSON_THROW_ON_ERROR);

        $this->notifier->broadcast($topic, $params);

        $io->success(sprintf('Broadcast of topic "%s" dispatched to all subscribers.', $topic));

        return Command::SUCCESS;
    }
}
