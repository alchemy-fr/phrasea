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
        $this->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Force the MIME type of file');
        $this->addOption('working-dir', 'w', InputOption::VALUE_REQUIRED, 'The working directory. Defaults to system temp directory');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output file name WITHOUT extension');
        $this->addOption('debug', 'd', InputOption::VALUE_NONE, 'set to debug mode (keep files in working directory)');
        $this->setHelp("Create a rendition from a source file and a build config\n"
            ."without --debug, the working directory will be cleaned up after the rendition is created,\n"
            ." so to get the final rendition, one must set --output or/and --debug\n"
            ."--output will move the final rendition from the working directory to the specified location ; Extension is added accordingly of (last) module).\n"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ret = Command::SUCCESS;
        $src = $input->getArgument('src');
        if (is_dir($src)) {
            if (false === ($od = opendir($src))) {
                $output->writeln(sprintf('Directory "%s" could not be opened.', $src));

                return Command::FAILURE;
            }
            while ($f = readdir($od)) {
                if ('.' === $f || '..' === $f) {
                    continue;
                }
                if(false === $this->doFile($input, $output, $src.'/'.$f)) {
                    $ret = Command::FAILURE;
                }
            }
            closedir($od);
        } else {
            if(false === $this->doFile($input, $output, $src)) {
                $ret = Command::FAILURE;
            }
        }

        return $ret;
    }

    protected function doFile(InputInterface $input, OutputInterface $output, string $src): bool
    {
        if (!file_exists($src)) {
            $output->writeln(sprintf('File "%s" does not exist.', $src));

            return false;
        }

        $time = microtime(true);
        $output->writeln('');
        $output->writeln(sprintf('Processing file: %s', $src));

        $mimeType = $input->getOption('type');

        if (null === $mimeType) {
            $mimeType = $this->mimeTypeGuesser->guessMimeTypeFromPath($src);
            $output->writeln(sprintf('MIME type guessed: %s', $mimeType ?? 'unknown'));
        }

        $buildConfig = $this->yamlLoader->load($input->getArgument('build-config'));

        $options = new CreateRenditionOptions(
            workingDirectory: $input->getOption('working-dir')
        );

        try {
            $outputFile = $this->renditionCreator->createRendition(
                $src,
                $mimeType,
                $buildConfig,
                $options
            );
            $output->writeln(sprintf('Rendition created: %s', $outputFile->getPath()));

        } catch (\InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return false;
        }

        if ($outputPath = $input->getOption('output')) {
            if ('/' === substr($outputPath, -1)) {
                // a directory is specified, use the filename of the source
                $outputPath .= pathinfo($src, PATHINFO_FILENAME);
            }
            @mkdir(dirname($outputPath), 0755, true);
            $outputPath .= '.'.$outputFile->getExtension();
            rename($outputFile->getPath(), $outputPath);
            $output->writeln(sprintf('Rendition moved to: %s', $outputPath));
        }

        if ($src === $outputFile->getPath()) {
            $output->writeln('No transformation needed');

            return false;
        }

        if (!$input->getOption('debug')) {
            $this->renditionCreator->cleanUp();
        }

        $output->writeln(sprintf('Execution time: %0.2f', microtime(true) - $time));

        return true;
    }
}
