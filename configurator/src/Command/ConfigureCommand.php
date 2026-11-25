<?php

declare(strict_types=1);

namespace App\Command;

use App\Configurator\Configurator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'configure', description: 'Configure phrasea stack.')]
final class ConfigureCommand extends Command
{
    public function __construct(
        private readonly Configurator $configurator,
    ) {
        parent::__construct();
    }

    /**
     * @param string[] $presets
     * @param string[] $filters
     */
    public function __invoke(
        OutputInterface $output,
        #[Option(name: 'preset')]
        array $presets = [],
        #[Option(name: 'filter')]
        array $filters = [],
    ): int {
        $this->configurator->configure($output, $presets, filters: $filters);

        return Command::SUCCESS;
    }
}
