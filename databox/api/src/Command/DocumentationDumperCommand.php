<?php

declare(strict_types=1);

namespace App\Command;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        private readonly RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('# '.$this->renditionBuilderConfigurationDocumentation::getName());
        $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());

        return Command::SUCCESS;
    }
}
