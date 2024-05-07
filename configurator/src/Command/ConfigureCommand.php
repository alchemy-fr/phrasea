<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Configurator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'configure', description: 'Configure phrasea stack.')]
final class ConfigureCommand extends Command
{
    public function __construct(
        private readonly Configurator $configurator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('preset', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $presets = $input->getOption('preset');

        $this->configurator->configure($output, $presets);

        return Command::SUCCESS;
    }
}
