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

            $outputDir = __DIR__.'/../../_generatedDoc';

            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                    $output->writeln('<error>Failed to create output directory: '.$outputDir.'</error>');

                    return Command::FAILURE;
                }
            }

            $result = file_put_contents(
                $outputDir.'/'.$outputFilename,
                '# '.$this->renditionBuilderConfigurationDocumentation::getName()."\n\n".
                $this->renditionBuilderConfigurationDocumentation->generate()
            );

            if (false === $result) {
                $output->writeln('<error>Failed to save documentation to '.$outputDir.'/'.$outputFilename.'</error>');

                return Command::FAILURE;
            }

            $output->writeln('Documentation saved to '.$outputDir.'/'.$outputFilename);
        } else {
            $output->writeln($this->renditionBuilderConfigurationDocumentation->generate());
        }

        return Command::SUCCESS;
    }
}
