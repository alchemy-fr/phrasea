<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
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

        $this->addArgument('src', InputArgument::REQUIRED, 'The source file');
        $this->addArgument('build-config', InputArgument::REQUIRED, 'The build config YAML file');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'The MIME type of file');
        $this->addOption('working-dir', 'w', InputOption::VALUE_REQUIRED, 'The working directory. Defaults to system temp directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mimeType = $input->getOption('type');
        if ($mimeType === null) {
            $mimeType = $this->mimeTypeGuesser->guessMimeTypeFromPath($input->getArgument('src'));
        }

        $buildConfig = $this->yamlLoader->load($input->getArgument('build-config'));

        $options = new CreateRenditionOptions(
            workingDirectory: $input->getOption('working-dir')
        );

        $outputFile = $this->renditionCreator->createRendition(
            $input->getArgument('src'),
            $mimeType,
            $buildConfig,
            $options
        );

        $output->writeln(sprintf('Rendition created: %s', $outputFile->getPath()));

        return 0;
    }
}
