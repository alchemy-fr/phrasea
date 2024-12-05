<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;


#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        private readonly RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('# ' . $this->renditionBuilderConfigurationDocumentation::getName());
        $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());

        return 0;
    }
}
