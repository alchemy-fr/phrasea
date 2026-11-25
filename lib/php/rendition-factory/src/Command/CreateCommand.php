<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Command;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'alchemy:rendition-factory:create',
    help: "Create a rendition from a source file and a build config\n"
        ."without --debug, the working directory will be cleaned up after the rendition is created,\n"
        ." so to get the final rendition, one must set --output or/and --debug\n"
        ."--output will move the final rendition from the working directory to the specified location ; Extension is added accordingly of (last) module).\n",
)]
class CreateCommand extends Command
{
    public function __construct(
        private readonly RenditionCreator $renditionCreator,
        private readonly YamlLoader $yamlLoader,
        private readonly MimeTypeGuesser $mimeTypeGuesser,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The source file')]
        string $src,
        #[Argument('The build config YAML file')]
        string $buildConfig,
        #[Option('Force the MIME type of file', shortcut: 't')]
        ?string $type = null,
        #[Option('The working directory. Defaults to system temp directory', shortcut: 'w')]
        ?string $workingDir = null,
        #[Option('The output file name WITHOUT extension', name: 'output', shortcut: 'o')]
        ?string $outputPath = null,
        #[Option('set to debug mode (keep files in working directory)', shortcut: 'd')]
        bool $debug = false,
    ): int {
        $doFile = fn (string $file): bool => $this->doFile($output, $file, $buildConfig, $type, $workingDir, $outputPath, $debug);

        $ret = Command::SUCCESS;
        if (is_dir($src)) {
            if (false === ($od = opendir($src))) {
                $output->writeln(sprintf('Directory "%s" could not be opened.', $src));

                return Command::FAILURE;
            }
            while ($f = readdir($od)) {
                if ('.' === $f || '..' === $f) {
                    continue;
                }
                if (false === $doFile($src.'/'.$f)) {
                    $ret = Command::FAILURE;
                }
            }
            closedir($od);
        } else {
            if (false === $doFile($src)) {
                $ret = Command::FAILURE;
            }
        }

        return $ret;
    }

    protected function doFile(
        OutputInterface $output,
        string $src,
        string $buildConfigFile,
        ?string $mimeType,
        ?string $workingDir,
        ?string $outputPath,
        bool $debug,
    ): bool {
        if (!file_exists($src)) {
            $output->writeln(sprintf('File "%s" does not exist.', $src));

            return false;
        }

        $time = microtime(true);
        $output->writeln('');
        $output->writeln(sprintf('Processing file: %s', $src));

        if (null === $mimeType) {
            $mimeType = $this->mimeTypeGuesser->guessMimeTypeFromPath($src);
            $output->writeln(sprintf('MIME type guessed: %s', $mimeType ?? 'unknown'));
        }

        $buildConfig = $this->yamlLoader->load($buildConfigFile);

        $options = new CreateRenditionOptions(
            workingDirectory: $workingDir
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

        if ($outputPath) {
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

        if (!$debug) {
            $this->renditionCreator->cleanUp();
        }

        $output->writeln(sprintf('Execution time: %0.2f', microtime(true) - $time));

        return true;
    }
}
