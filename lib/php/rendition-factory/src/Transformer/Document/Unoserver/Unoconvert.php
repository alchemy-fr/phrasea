<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Unoserver;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

final class Unoconvert
{
    private string $binaryPath;

    public function __construct()
    {
        $this->binaryPath = (new ExecutableFinder())->find('unoconvert');
    }

    /** 
     *  pageRange  the interval page to convert eg: pageRange=1-5
     */
    public function transcode(string $inputPath, string $outputPath, ?string $pageRange = null): void
    {
        $args = [
            $this->binaryPath,
            $inputPath,
            $outputPath,
        ];

        @unlink($outputPath);

        if ($pageRange !== null) {
            $args[] = "--filter-options";
            $args[] = 'PageRange=' . $pageRange;
        }

        $process = new Process($args);

        $process->mustRun(); // throw exception when failed
    }

} 
