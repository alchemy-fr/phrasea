<?php

declare(strict_types=1);

namespace App\Command;

use App\User\Import\UserImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportUsersCommand extends Command
{
    /**
     * @var UserImporter
     */
    private $userImporter;

    public function __construct(UserImporter $userImporter)
    {
        parent::__construct();

        $this->userImporter = $userImporter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:user:import')
            ->setDescription('Import users')
            ->addArgument('src', InputArgument::REQUIRED, 'The file to import');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $src = $input->getArgument('src');

        $count = $this->userImporter->import($src);

        $output->writeln(sprintf('%d users imported.', $count));

        return 0;
    }
}
