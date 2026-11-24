<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rendition;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Exception\UnsupportedSourceFileException;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\RenditionFactory\Transformer\Document\DocumentToPdfTransformerModule;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\DocumentFormat;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\DocumentFormatDetector;
use Alchemy\RenditionFactory\Transformer\Document\Libreoffice\PdfConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;

class DocumentToPdfTest extends TestCase
{
    private const string SECRET_VARIABLE = 'RENDITION_TEST_SECRET';

    private string $workDir;
    private string $reportDir;
    private RenditionCreator $creator;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('document-to-pdf-test-');
        $this->reportDir = $this->workDir.'/report';
        mkdir($this->workDir.'/inputs', recursive: true);
        mkdir($this->reportDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->creator)) {
            $this->creator->cleanUp();
        }
        exec('rm -rf '.escapeshellarg($this->workDir));
        putenv(self::SECRET_VARIABLE);
        unset($_ENV[self::SECRET_VARIABLE], $_SERVER[self::SECRET_VARIABLE]);
    }

    public static function getSupportedDocuments(): iterable
    {
        yield 'docx' => ['docx', DocumentFormat::Docx];
        yield 'odt' => ['odt', DocumentFormat::Odt];
        yield 'rtf' => ['rtf', DocumentFormat::Rtf];
        yield 'txt' => ['txt', DocumentFormat::Text];
    }

    #[DataProvider('getSupportedDocuments')]
    public function testFormatIsToldFromTheContent(string $kind, DocumentFormat $expected): void
    {
        $this->assertSame($expected, (new DocumentFormatDetector())->detect($this->createDocument($kind)));
    }

    public static function getUnsupportedDocuments(): iterable
    {
        yield 'html' => ['html', 'text/html'];
        yield 'flat odt' => ['fodt', 'application/vnd.oasis.opendocument.text'];
        yield 'svg' => ['svg', 'image/svg+xml'];
        yield 'random binary' => ['binary', 'application/msword'];
        yield 'zip archive' => ['zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        yield 'empty file' => ['empty', 'text/plain'];
    }

    #[DataProvider('getUnsupportedDocuments')]
    public function testUnsupportedContentIsRefusedWithoutLaunchingLibreoffice(string $kind, string $declaredType): void
    {
        $this->createCreator(new PdfConverter($this->createFakeLibreoffice()));

        try {
            $this->convert($this->createDocument($kind), $declaredType);
            $this->fail('An unsupported source file must be refused');
        } catch (UnsupportedSourceFileException) {
        }

        $this->assertFileDoesNotExist($this->reportDir.'/args', 'LibreOffice must not be launched');
    }

    public function testPdfIsPassedThroughWhateverItsDeclaredType(): void
    {
        $this->createCreator(new PdfConverter($this->createFakeLibreoffice()));
        $path = $this->workDir.'/inputs/document.bin';
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        $output = $this->convert($path, 'application/octet-stream');

        $this->assertSame($path, $output->getPath());
        $this->assertSame('application/pdf', $output->getType());
        $this->assertFileDoesNotExist($this->reportDir.'/args');
    }

    public function testLibreofficeRunsWithADisposableProfileAndNothingFromTheWorker(): void
    {
        $this->createCreator(new PdfConverter($this->createFakeLibreoffice()));
        putenv(self::SECRET_VARIABLE.'=s3cr3t');
        $_ENV[self::SECRET_VARIABLE] = 's3cr3t';
        $_SERVER[self::SECRET_VARIABLE] = 's3cr3t';

        $output = $this->convert($this->createDocument('docx'), 'application/octet-stream');

        $this->assertSame('application/pdf', $output->getType());
        $this->assertStringStartsWith('%PDF-', file_get_contents($output->getPath()));

        $env = $this->readEnv();
        $this->assertArrayNotHasKey(self::SECRET_VARIABLE, $env);
        $expected = ['ALL_PROXY', 'FTP_PROXY', 'HOME', 'HTTPS_PROXY', 'HTTP_PROXY', 'LANG', 'LC_ALL', 'NO_PROXY', 'PATH', 'SAL_USE_VCLPLUGIN', 'TMPDIR', 'all_proxy', 'ftp_proxy', 'http_proxy', 'https_proxy', 'no_proxy'];
        $setByTheShell = ['OLDPWD', 'PWD', 'SHLVL', '_'];
        $this->assertSame([], array_values(array_diff(array_keys($env), $expected, $setByTheShell)), 'Only the variables set for LibreOffice reach it');
        $this->assertSame([], array_values(array_diff($expected, array_keys($env))));
        $this->assertSame('http://127.0.0.1:9', $env['http_proxy']);
        $this->assertSame('', $env['no_proxy']);

        $conversionDir = trim(file_get_contents($this->reportDir.'/cwd'));
        $this->assertSame($conversionDir.'/profile', $env['HOME']);
        $this->assertSame($conversionDir.'/tmp', $env['TMPDIR']);
        $this->assertStringStartsWith(dirname($output->getPath()).'/', $conversionDir, 'The conversion runs in the working directory of the transformation');
        $this->assertDirectoryDoesNotExist($conversionDir, 'The conversion directory is removed');

        $this->assertSame("yes\n", file_get_contents($this->reportDir.'/profile'), 'The hardened profile is in place');

        $args = file($this->reportDir.'/args', FILE_IGNORE_NEW_LINES);
        $this->assertContains('-env:UserInstallation=file://'.$conversionDir.'/profile', $args);
        $this->assertContains('--infilter=MS Word 2007 XML', $args);
        foreach (['--headless', '--norestore', '--nolockcheck', '--nologo', '--nodefault'] as $flag) {
            $this->assertContains($flag, $args);
        }
        $this->assertSame($conversionDir.'/document.docx', end($args), 'The input is renamed after its detected format');
    }

    public function testEachConversionHasItsOwnProfile(): void
    {
        $this->createCreator(new PdfConverter($this->createFakeLibreoffice()));

        $this->convert($this->createDocument('rtf'), 'text/rtf');
        $firstHome = $this->readEnv()['HOME'];
        $this->convert($this->createDocument('rtf'), 'text/rtf');
        $secondHome = $this->readEnv()['HOME'];

        $this->assertNotSame($firstHome, $secondHome);
        $this->assertDirectoryDoesNotExist($firstHome);
        $this->assertDirectoryDoesNotExist($secondHome);
        $this->assertContains('--infilter=Rich Text Format', file($this->reportDir.'/args', FILE_IGNORE_NEW_LINES));
    }

    public function testTimeoutKillsTheWholeProcessTree(): void
    {
        $this->createCreator(new PdfConverter($this->createFakeLibreoffice(hang: true)));

        try {
            $this->convert($this->createDocument('txt'), 'text/plain', ['timeout' => 1]);
            $this->fail('The conversion must time out');
        } catch (ProcessTimedOutException) {
        }

        $pids = array_map(intval(...), file($this->reportDir.'/pids', FILE_IGNORE_NEW_LINES));
        $this->assertCount(2, $pids);
        $this->waitForProcessesToEnd($pids);
        foreach ($pids as $pid) {
            $this->assertFalse($this->isProcessAlive($pid), sprintf('Process %d survived the timeout', $pid));
        }
    }

    public function testTimeoutOptionIsValidated(): void
    {
        $validator = new BuildConfigValidator(
            new ServiceLocator([DocumentToPdfTransformerModule::NAME => fn () => $this->createModule(new PdfConverter())]),
            new ServiceLocator([]),
        );

        $validator->validate($this->parse(['timeout' => 1800]));

        $this->expectException(InvalidConfigurationException::class);
        $validator->validate($this->parse(['timeout' => 1801]));
    }

    public static function getRealConversions(): iterable
    {
        yield 'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        yield 'odt' => ['odt', 'application/vnd.oasis.opendocument.text'];
        yield 'rtf' => ['rtf', 'text/rtf'];
        yield 'txt' => ['txt', 'text/plain'];
        yield 'docx declared as a PDF' => ['docx', 'application/pdf'];
    }

    #[DataProvider('getRealConversions')]
    public function testLibreofficeConvertsTheDocument(string $kind, string $declaredType): void
    {
        $this->createCreator($this->createRealConverter());

        $output = $this->convert($this->createDocument($kind), $declaredType, ['timeout' => 120]);

        $this->assertSame('application/pdf', $output->getType());
        $this->assertStringStartsWith('%PDF-', file_get_contents($output->getPath()));
        $this->assertSame(
            [basename($output->getPath())],
            array_values(array_diff(scandir(dirname($output->getPath())), ['.', '..'])),
            'Nothing is left in the working directory but the PDF',
        );
        $this->assertSame([], $this->findLibreofficeProcesses(), 'No LibreOffice process survives the conversion');
    }

    public static function getLinkedImageDocuments(): iterable
    {
        yield 'docx' => ['docx'];
        yield 'odt' => ['odt'];
    }

    #[DataProvider('getLinkedImageDocuments')]
    public function testLinkedImagesAreNotFetched(string $kind): void
    {
        $this->createCreator($this->createRealConverter());

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, $errstr);
        stream_set_blocking($server, false);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        try {
            $path = $this->workDir.'/inputs/'.uniqid('linked-').'.'.$kind;
            'docx' === $kind
                ? self::writeDocx($path, 'Linked image', sprintf('http://127.0.0.1:%d/image.png', $port))
                : self::writeOdt($path, 'Linked image', sprintf('http://127.0.0.1:%d/image.png', $port));

            $output = $this->convert($path, 'application/octet-stream', ['timeout' => 120]);

            $this->assertStringStartsWith('%PDF-', file_get_contents($output->getPath()));
            $this->assertFalse(@stream_socket_accept($server, 0), 'LibreOffice must not reach the network');
        } finally {
            fclose($server);
        }
    }

    public static function writeDocx(string $path, string $text, ?string $linkedImageUrl = null): void
    {
        $relationships = '';
        $drawing = '';
        if (null !== $linkedImageUrl) {
            $relationships = sprintf(
                '<Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="%s" TargetMode="External"/>',
                htmlspecialchars($linkedImageUrl, ENT_XML1),
            );
            $drawing = <<<'XML'
<w:p><w:r><w:drawing><wp:inline><wp:extent cx="952500" cy="952500"/><wp:docPr id="1" name="Picture 1"/>
<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>
<pic:nvPicPr><pic:cNvPr id="1" name="image.png"/><pic:cNvPicPr/></pic:nvPicPr>
<pic:blipFill><a:blip r:link="rIdImage"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="952500" cy="952500"/></a:xfrm><a:prstGeom prst="rect"/></pic:spPr>
</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>
XML;
        }

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
        $zip->addFromString('word/_rels/document.xml.rels', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">$relationships</Relationships>
XML);
        $text = htmlspecialchars($text, ENT_XML1);
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
    xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
    xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
<w:body><w:p><w:r><w:t>$text</w:t></w:r></w:p>$drawing</w:body>
</w:document>
XML);
        $zip->close();
    }

    public static function writeOdt(string $path, string $text, ?string $linkedImageUrl = null): void
    {
        $text = htmlspecialchars($text, ENT_XML1);
        $image = null === $linkedImageUrl ? '' : sprintf(
            '<draw:frame draw:name="image" text:anchor-type="as-char" svg:width="2cm" svg:height="2cm"><draw:image xlink:href="%s" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/></draw:frame>',
            htmlspecialchars($linkedImageUrl, ENT_XML1),
        );

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/manifest.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML);
        $zip->addFromString('content.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
    xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
    xmlns:xlink="http://www.w3.org/1999/xlink" office:version="1.3">
<office:body><office:text><text:p>$text$image</text:p></office:text></office:body>
</office:document-content>
XML);
        $zip->close();
    }

    private function createDocument(string $kind): string
    {
        $path = $this->workDir.'/inputs/'.uniqid('document-').'.'.$kind;

        match ($kind) {
            'docx' => self::writeDocx($path, 'Hello from Word'),
            'odt' => self::writeOdt($path, 'Hello from Writer'),
            'rtf' => file_put_contents($path, '{\rtf1\ansi Hello from RTF\par}'),
            'txt' => file_put_contents($path, "Hello from a plain text file\nSecond line\n"),
            'html' => file_put_contents($path, '<!DOCTYPE html><html><body><p>Hello</p><img src="http://127.0.0.1:9/image.png"></body></html>'),
            'fodt' => file_put_contents($path, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    office:version="1.3" office:mimetype="application/vnd.oasis.opendocument.text">
<office:body><office:text><text:p>Hello</text:p></office:text></office:body>
</office:document>
XML),
            'svg' => file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>'),
            'binary' => file_put_contents($path, "\x00\x01\x02\x03".random_bytes(4096)),
            'zip' => $this->writeZip($path),
            'empty' => touch($path),
        };

        return $path;
    }

    private function writeZip(string $path): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'Not an office document');
        $zip->close();
    }

    /**
     * A stand-in for LibreOffice reporting what it was given: arguments, environment,
     * working directory and profile; it writes a fake PDF where LibreOffice would.
     */
    private function createFakeLibreoffice(bool $hang = false): string
    {
        $report = escapeshellarg($this->reportDir);
        $body = $hang
            ? <<<SH
sleep 60 &
echo \$\$ > $report/pids
echo \$! >> $report/pids
sleep 60
SH
            : <<<SH
out=""
prev=""
for arg in "\$@"; do
    if [ "\$prev" = "--outdir" ]; then out="\$arg"; fi
    prev="\$arg"
done
printf '%s\\n' "\$@" > $report/args
env > $report/env
pwd > $report/cwd
if [ -f "\$HOME/user/registrymodifications.xcu" ]; then echo yes > $report/profile; else echo no > $report/profile; fi
printf '%%PDF-1.4 fake\\n' > "\$out/document.pdf"
SH;

        $path = $this->workDir.'/fake-libreoffice';
        file_put_contents($path, "#!/bin/sh\n".$body."\n");
        chmod($path, 0755);

        return $path;
    }

    private function createRealConverter(): PdfConverter
    {
        if (null === (new ExecutableFinder())->find('libreoffice')) {
            $this->markTestSkipped('LibreOffice is not installed');
        }

        return new PdfConverter();
    }

    private function createModule(PdfConverter $converter): DocumentToPdfTransformerModule
    {
        return new DocumentToPdfTransformerModule($converter, new DocumentFormatDetector());
    }

    private function createCreator(PdfConverter $converter): void
    {
        $this->creator = new RenditionCreator(
            new TransformationContextFactory(new MimeTypeGuesser(), $this->workDir.'/rendition'),
            new FileFamilyGuesser(),
            new ServiceLocator([DocumentToPdfTransformerModule::NAME => fn () => $this->createModule($converter)]),
            new ServiceLocator([]),
        );
    }

    private function convert(string $path, string $declaredType, array $options = []): OutputFileInterface
    {
        return $this->creator->createRendition($path, $declaredType, $this->parse($options));
    }

    private function parse(array $options): BuildConfig
    {
        $options = json_encode((object) $options, JSON_THROW_ON_ERROR);

        return (new YamlLoader())->parse(<<<YAML
document: &transformations
    transformations:
        - module: document_to_pdf
          options: $options
image: *transformations
unknown: *transformations

YAML);
    }

    /**
     * @return array<string, string>
     */
    private function readEnv(): array
    {
        $env = [];
        foreach (file($this->reportDir.'/env', FILE_IGNORE_NEW_LINES) as $line) {
            [$name, $value] = explode('=', $line, 2);
            $env[$name] = $value;
        }
        ksort($env, SORT_STRING);

        return $env;
    }

    /**
     * @param int[] $pids
     */
    private function waitForProcessesToEnd(array $pids): void
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline && array_filter($pids, $this->isProcessAlive(...))) {
            usleep(50_000);
        }
    }

    private function isProcessAlive(int $pid): bool
    {
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if (false === $stat) {
            return false;
        }

        // A zombie is dead, only waiting for its parent to reap it
        return 'Z' !== substr($stat, strrpos($stat, ')') + 2, 1);
    }

    /**
     * @return int[]
     */
    private function findLibreofficeProcesses(): array
    {
        $pids = [];
        foreach (glob('/proc/[0-9]*/comm') as $comm) {
            $pid = (int) basename(dirname($comm));
            $name = (string) @file_get_contents($comm);
            if ((str_starts_with($name, 'soffice') || str_starts_with($name, 'oosplash')) && $this->isProcessAlive($pid)) {
                $pids[] = $pid;
            }
        }

        return $pids;
    }
}
