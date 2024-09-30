<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Libreoffice;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PdfConverter
{
    private ?string $binaryPath = null;

    public function convert(string $inputPath, string $outputPath): void
    {
        $inputInfo = new \SplFileInfo($inputPath);
        $filename = $inputInfo->getBasename('.'.$inputInfo->getExtension());

        $outputInfo = new \SplFileInfo($outputPath);
        $outDir = $outputInfo->getPathInfo()->getRealPath();

        $args = [
            $this->getBinaryPath(),
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outDir,
            $inputPath,
        ];

        $process = new Process($args);

        $process->setTimeout(3600);

        $process->mustRun(); // throw exception when failed

        $generatedFile = $outDir.'/'.$filename.'.pdf';
        $filesystem = new Filesystem();

        if ($filesystem->exists($generatedFile)) {
            $filesystem->rename($generatedFile, $outputPath);
        } else {
            throw new FileNotFoundException(sprintf('Generated file not found in %s', $generatedFile));
        }
    }

    private function getBinaryPath(): string
    {
        if (null === $this->binaryPath) {
            $this->binaryPath = (new ExecutableFinder())->find('libreoffice');
        }

        return $this->binaryPath;
    }
}
