<?php

declare(strict_types=1);

namespace App\Command;

use App\User\Import\UserImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportUsersCommand extends Command
{
    public function __construct(private readonly UserImporter $userImporter)
    {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:user:import')
            ->setDescription('Import users')
            ->addArgument('src', InputArgument::REQUIRED, 'The file to import')
            ->addOption('invite', null, InputOption::VALUE_NONE, 'Invite all users by email after import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $src = $input->getArgument('src');
        $inviteUsers = (bool) $input->getOption('invite');

        $count = $this->userImporter->import($src, $inviteUsers, $violations);

        if (!empty($violations)) {
            $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            foreach ($violations as $violation) {
                $errOutput->writeln($violation);
            }
        }

        $output->writeln(sprintf('%d users imported.', $count));

        return 0;
    }
}
