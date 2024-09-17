<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Libreoffice;

use SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;

final class PdfConverter
{
    private string $binaryPath;

    public function __construct()
    {
        $this->binaryPath = (new ExecutableFinder())->find('libreoffice');
    }

    public function convert(string $inputPath, string $outputPath): void
    {
        $inputInfo = new SplFileInfo($inputPath);
        $filename = $inputInfo->getBasename('.' .$inputInfo->getExtension());

        $outputInfo = new SplFileInfo($outputPath);
        $outDir = ($outputInfo->getPathInfo())->getRealPath();

        $args = [
            $this->binaryPath,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outDir,
            $inputPath,
        ];

        $process = new Process($args);

        $process->mustRun(); // throw exception when failed

        $generatedFile = $outDir . '/' .  $filename. '.pdf';
        $filesystem = new Filesystem();
        
        if ($filesystem->exists($generatedFile)) {
            $filesystem->copy($generatedFile, $outputPath);
            if ($generatedFile !== $outputPath) {
                @unlink($generatedFile);
            }
        }
    }

} 
