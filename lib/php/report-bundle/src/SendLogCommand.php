<?php

namespace Alchemy\ReportBundle;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:report:send-log')]
class SendLogCommand extends Command
{
    public function __construct(
        private readonly ReportUserService $reportUserService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->reportUserService->pushLog(
            'test_action',
            'test-item',
            ['foo' => 'bar']
        );

        return Command::SUCCESS;
    }
}
