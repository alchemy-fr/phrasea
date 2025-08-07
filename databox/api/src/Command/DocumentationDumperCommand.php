<?php

declare(strict_types=1);

namespace App\Command;

use Alchemy\RenditionFactory\RenditionBuilderConfigurationDocumentation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:documentation:dump')]
class DocumentationDumperCommand extends Command
{
    public function __construct(
        private readonly RenditionBuilderConfigurationDocumentation $renditionBuilderConfigurationDocumentation,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('output-filename', 'f', InputOption::VALUE_REQUIRED, 'The output filename where the documentation will be saved. eg: documentation.md')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('output-filename')) {

            $outputFilename = $input->getOption('output-filename');

            $outputDir = __DIR__.'/../../generatedDoc';
            mkdir($outputDir, 0755, true);

            file_put_contents(
                $outputDir.'/'.$outputFilename,
                '# '.$this->renditionBuilderConfigurationDocumentation::getName()."\n\n".
                $this->renditionBuilderConfigurationDocumentation->generate()
            );

            $output->writeln('Documentation saved to '.$outputDir.'/'.$outputFilename);
        } else {
            $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());
        }

        return Command::SUCCESS;
    }
}
