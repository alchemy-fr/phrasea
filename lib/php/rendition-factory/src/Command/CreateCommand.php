<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('alchemy:rendition-factory:create')]
class CreateCommand extends Command
{
    public function __construct(
        private readonly RenditionCreator $renditionCreator,
        private readonly YamlLoader $yamlLoader,
        private readonly MimeTypeGuesser $mimeTypeGuesser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'The source file');
        $this->addOption('build-config', 'c', InputOption::VALUE_REQUIRED, 'The build config YAML file');
        $this->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The MIME type of file');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output file name WITHOUT extension');
        $this->addOption('working-dir', 'w', InputOption::VALUE_REQUIRED, 'The working directory. Defaults to system temp directory');
        $this->addOption('debug', 'd', InputOption::VALUE_NONE, 'set to debug mode (keep files in working directory)');
        $this->setHelp("Create a rendition from a source file and a build config\n"
            . "without --debug, the working directory will be cleaned up after the rendition is created,\n"
            . " so to get the final rendition, one must set --output or/and --debug\n"
            . "--output will move the final rendition from the working directory to the specified location ; Extension is added accordingly of (last) module).\n"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if(!file_exists($input->getOption('input'))) {
            $output->writeln(sprintf('Input file not found: %s', $input->getOption('input')));
            return 1;
        }

        $mimeType = $input->getOption('type');
        if ($mimeType === null) {
            $mimeType = $this->mimeTypeGuesser->guessMimeTypeFromPath($input->getArgument('input'));
        }

        $buildConfig = $this->yamlLoader->load($input->getOption('build-config'));

        $options = new CreateRenditionOptions(
            workingDirectory: $input->getOption('working-dir'),
        );

        $outputFile = $this->renditionCreator->createRendition(
            $input->getOption('input'),
            $mimeType,
            $buildConfig,
            $options
        );

        $output->writeln(sprintf('Rendition created: %s', $outputFile->getPath()));

        if ( ($outputPath = $input->getOption('output')) ) {
            $pi = pathinfo($outputPath);
            @mkdir($pi['dirname'], 0777, true);
            $outputPath .= '.' . $outputFile->getExtension();
            rename($outputFile->getPath(), $outputPath);
            $output->writeln(sprintf('Rendition moved to: %s', $outputPath));
        }

        if(!$input->getOption('debug')) {
            $this->renditionCreator->cleanUp();
        }

        return 0;
    }
}
