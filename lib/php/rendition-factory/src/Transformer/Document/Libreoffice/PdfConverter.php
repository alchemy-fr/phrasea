<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Libreoffice;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs LibreOffice on documents that may be hostile, without a kernel sandbox:
 *
 * - a new profile per conversion, copied from profile/ (no macro, no link update, HTTP sent to a
 *   closed loopback port), deleted afterwards;
 * - an empty environment (none of the worker secrets), a working directory of its own;
 * - the import filter forced, so that LibreOffice never guesses the format from the content;
 * - a timeout, after which the whole process group is killed (soffice.bin is a grandchild).
 */
final class PdfConverter
{
    public const int DEFAULT_TIMEOUT = 300;
    public const int MAX_TIMEOUT = 1800;

    private const int SIGKILL = 9;
    private const string BLACKHOLE_PROXY = 'http://127.0.0.1:9';
    private const string PATH = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

    private ?string $envPath = null;
    private ?string $setsidPath = null;
    private bool $setsidResolved = false;

    public function __construct(
        private ?string $binaryPath = null,
    ) {
    }

    /**
     * @param string $workDir a directory that does not exist yet, created and removed by the conversion
     */
    public function convert(
        string $inputPath,
        DocumentFormat $format,
        string $outputPath,
        string $workDir,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): void {
        if ($timeout < 1 || $timeout > self::MAX_TIMEOUT) {
            throw new \InvalidArgumentException(sprintf('LibreOffice timeout must be between 1 and %d seconds, %d given', self::MAX_TIMEOUT, $timeout));
        }

        $filesystem = new Filesystem();
        $filesystem->mkdir($workDir, 0700);

        try {
            $profileDir = $workDir.'/profile';
            $tmpDir = $workDir.'/tmp';
            $outDir = $workDir.'/out';
            $filesystem->mirror(__DIR__.'/profile', $profileDir);
            $filesystem->mkdir([$tmpDir, $outDir], 0700);

            $source = $workDir.'/document.'.$format->getExtension();
            $filesystem->copy($inputPath, $source);

            $this->run($format, $source, $profileDir, $tmpDir, $outDir, $workDir, $timeout);

            $generatedFile = $outDir.'/document.pdf';
            if (!is_file($generatedFile)) {
                throw new \RuntimeException('LibreOffice did not produce any PDF');
            }

            $filesystem->rename($generatedFile, $outputPath, true);
        } finally {
            $filesystem->remove($workDir);
        }
    }

    private function run(
        DocumentFormat $format,
        string $source,
        string $profileDir,
        string $tmpDir,
        string $outDir,
        string $workDir,
        int $timeout,
    ): void {
        $setsid = $this->getSetsidPath();

        $command = [
            $this->getEnvPath(),
            '-i',
            'PATH='.self::PATH,
            'HOME='.$profileDir,
            'TMPDIR='.$tmpDir,
            'LANG=C.UTF-8',
            'LC_ALL=C.UTF-8',
            'SAL_USE_VCLPLUGIN=svp',
            'http_proxy='.self::BLACKHOLE_PROXY,
            'https_proxy='.self::BLACKHOLE_PROXY,
            'ftp_proxy='.self::BLACKHOLE_PROXY,
            'all_proxy='.self::BLACKHOLE_PROXY,
            'HTTP_PROXY='.self::BLACKHOLE_PROXY,
            'HTTPS_PROXY='.self::BLACKHOLE_PROXY,
            'FTP_PROXY='.self::BLACKHOLE_PROXY,
            'ALL_PROXY='.self::BLACKHOLE_PROXY,
            'no_proxy=',
            'NO_PROXY=',
        ];
        if (null !== $setsid) {
            $command[] = $setsid;
        }
        array_push(
            $command,
            $this->getBinaryPath(),
            '-env:UserInstallation='.self::toFileUrl($profileDir),
            '--headless',
            '--norestore',
            '--nolockcheck',
            '--nologo',
            '--nodefault',
            '--nofirststartwizard',
            '--infilter='.$format->getImportFilter(),
            '--convert-to',
            'pdf',
            '--outdir',
            $outDir,
            $source,
        );

        $process = new Process($command, $workDir, null, null, $timeout);
        $process->start();
        $pid = $process->getPid();

        try {
            $process->wait();
        } finally {
            // With setsid, LibreOffice leads its own process group: the group id is its pid
            if (null !== $setsid && null !== $pid) {
                @posix_kill(-$pid, self::SIGKILL);
            }
        }

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private static function toFileUrl(string $path): string
    {
        return 'file://'.implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    private function getEnvPath(): string
    {
        return $this->envPath ??= (new ExecutableFinder())->find('env')
            ?? throw new \RuntimeException('"env" binary not found');
    }

    private function getSetsidPath(): ?string
    {
        if (!$this->setsidResolved) {
            $this->setsidPath = \function_exists('posix_kill') ? (new ExecutableFinder())->find('setsid') : null;
            $this->setsidResolved = true;
        }

        return $this->setsidPath;
    }

    private function getBinaryPath(): string
    {
        if (null === $this->binaryPath) {
            $finder = new ExecutableFinder();
            $this->binaryPath = $finder->find('libreoffice') ?? $finder->find('soffice')
                ?? throw new \RuntimeException('LibreOffice binary ("libreoffice" or "soffice") not found');
        }

        return $this->binaryPath;
    }
}
