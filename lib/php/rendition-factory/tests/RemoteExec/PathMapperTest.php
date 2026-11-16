<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Tests\RemoteExec;

use Alchemy\RenditionFactory\RemoteExec\FileTree;
use Alchemy\RenditionFactory\RemoteExec\PathMapper;
use Alchemy\RenditionFactory\RemoteExec\Tool;
use Alchemy\RenditionFactory\RemoteExec\UnmappablePathException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathMapperTest extends TestCase
{
    private string $root;
    private string $worker;
    private string $job;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/'.uniqid('path-mapper-test', true);
        $this->worker = $this->root.'/worker';
        $this->job = $this->root.'/jobs/job';
        mkdir($this->worker.'/a', 0700, true);
        mkdir($this->job, 0700, true);
    }

    protected function tearDown(): void
    {
        FileTree::remove($this->root);
    }

    public function testExistingFileIsHardLinked(): void
    {
        $input = $this->createFile('a/in.mp4', 'video');

        $args = $this->createMapper()->map(Tool::Ffprobe, ['-show_format', '-i', $input]);

        $this->assertSame('-show_format', $args[0]);
        $this->assertStringStartsWith($this->job.'/', $args[2]);
        $this->assertSame('in.mp4', basename($args[2]));
        $this->assertSame(fileinode($input), fileinode($args[2]));
    }

    public function testSameFileIsMappedOnce(): void
    {
        $input = $this->createFile('a/in.jpg', 'image');

        $args = $this->createMapper()->map(Tool::Exiftool, ['-o', $input, '-overwrite_original_in_place', $input]);

        $this->assertSame($args[1], $args[3]);
    }

    public function testOutputIsBroughtBackAfterTheRun(): void
    {
        $input = $this->createFile('a/in.mp4', 'video');
        $output = $this->worker.'/a/out.webm';

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Ffmpeg, ['-y', '-i', $input, $output]);

        $this->assertFileDoesNotExist($output);
        $this->assertStringStartsWith($this->job.'/', $args[3]);
        $this->assertSame('out.webm', basename($args[3]));

        file_put_contents($args[3], 'converted');
        $mapper->syncBack();

        $this->assertSame('converted', file_get_contents($output));
        $this->assertSame('video', file_get_contents($input));
    }

    public function testChangeInPlaceNeedsNothingMore(): void
    {
        $input = $this->createFile('a/image.jpg', 'before');

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Exiftool, ['-overwrite_original_in_place', $input]);
        $handle = fopen($args[1], 'r+');
        fwrite($handle, 'after!');
        fclose($handle);
        $inode = fileinode($input);
        $mapper->syncBack();

        $this->assertSame('after!', file_get_contents($input));
        $this->assertSame($inode, fileinode($input));
    }

    public function testReplacedFileIsBroughtBack(): void
    {
        $input = $this->createFile('a/image.jpg', 'before');

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Exiftool, ['-overwrite_original', $input]);
        unlink($args[1]);
        file_put_contents($args[1], 'rewritten');
        $mapper->syncBack();

        $this->assertSame('rewritten', file_get_contents($input));
    }

    public function testFileUrlOfDirectoryIsMirrored(): void
    {
        $this->createFile('a/work/profile/user/registrymodifications.xcu', 'settings');
        $outDir = $this->worker.'/a/work/out';
        mkdir($outDir);
        $source = $this->createFile('a/work/document.docx', 'doc');
        $profileUrl = 'file://'.$this->worker.'/a/work/profile';

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Soffice, ['-env:UserInstallation='.$profileUrl, '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $source]);

        $this->assertMatchesRegularExpression('#^-env:UserInstallation=file://'.preg_quote($this->job, '#').'/\d+$#', $args[0]);
        $mirroredProfile = substr($args[0], strlen('-env:UserInstallation=file://'));
        $this->assertSame('settings', file_get_contents($mirroredProfile.'/user/registrymodifications.xcu'));
        $this->assertSame(['--headless', '--convert-to', 'pdf', '--outdir'], array_slice($args, 1, 4));
        $this->assertTrue(FileTree::isRealDirectory($args[5]));
        $this->assertStringStartsWith($this->job.'/', $args[6]);

        // LibreOffice writes its PDF in the output directory, and its settings in the profile
        file_put_contents($args[5].'/document.pdf', 'pdf');
        mkdir($mirroredProfile.'/user/cache');
        file_put_contents($mirroredProfile.'/user/cache/x', 'cache');
        $mapper->syncBack();

        $this->assertSame('pdf', file_get_contents($outDir.'/document.pdf'));
        $this->assertSame('cache', file_get_contents($this->worker.'/a/work/profile/user/cache/x'));
    }

    public function testEncodedFileUrl(): void
    {
        $this->createFile('a/my profile/x', 'x');

        $args = $this->createMapper()->map(Tool::Soffice, ['-env:UserInstallation=file://'.$this->worker.'/a/my%20profile']);

        $this->assertMatchesRegularExpression('#^-env:UserInstallation=file://'.preg_quote($this->job, '#').'/\d+$#', $args[0]);
    }

    public function testKeyEqualsPath(): void
    {
        $input = $this->createFile('a/in.png', 'png');

        $args = $this->createMapper()->map(Tool::Ffmpeg, ['-attach='.$input]);

        $this->assertStringStartsWith('-attach='.$this->job.'/', $args[0]);
        $this->assertSame(fileinode($input), fileinode(substr($args[0], strlen('-attach='))));
    }

    public function testPathEmbeddedInFilter(): void
    {
        $watermark = $this->createFile('cache/remote/0123abcd.png', 'png');

        $args = $this->createMapper()->map(Tool::Ffmpeg, ['-vf', 'movie='.$watermark.' [watermark]; [in][watermark] overlay=main_w - 10 - overlay_w:0 [out]']);

        $this->assertMatchesRegularExpression('#^movie='.preg_quote($this->job, '#').'/\d+/0123abcd\.png \[watermark\]; \[in\]\[watermark\] overlay=main_w - 10 - overlay_w:0 \[out\]$#', $args[1]);
    }

    public function testMissingPathEmbeddedInFilterIsRefused(): void
    {
        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Ffmpeg, ['-vf', 'movie='.$this->worker.'/a/missing.png [wm]; [in][wm] overlay=0:0 [out]']);
    }

    public function testPathWithSpaceEmbeddedInFilterIsRefused(): void
    {
        $this->createFile('a/my watermark.png', 'png');

        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Ffmpeg, ['-vf', 'movie='.$this->worker.'/a/my watermark.png [wm]; [in][wm] overlay=0:0 [out]']);
    }

    public function testExactPathWithSpace(): void
    {
        $input = $this->createFile('a/my video.mp4', 'video');

        $args = $this->createMapper()->map(Tool::Ffprobe, [$input]);

        $this->assertSame(fileinode($input), fileinode($args[0]));
    }

    public function testExiftoolWriteFormatInDirectory(): void
    {
        $input = $this->createFile('a/in.ai', 'ai');
        mkdir($this->worker.'/a/extracted');

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Exiftool, ['-b', '-PreviewImage', '-w', $this->worker.'/a/extracted/PreviewImage%c.jpg', '-common_args', '-q', $input]);

        $this->assertSame('PreviewImage%c.jpg', basename($args[3]));
        file_put_contents(dirname($args[3]).'/PreviewImage.jpg', 'preview');
        $mapper->syncBack();

        $this->assertSame('preview', file_get_contents($this->worker.'/a/extracted/PreviewImage.jpg'));
    }

    public function testPassLogFilesOfAnEarlierPassAreLinked(): void
    {
        $input = $this->createFile('a/in.mp4', 'video');
        $prefix = $this->createFile('a/ffmpeg-passes1/pass-1', '');
        $this->createFile('a/ffmpeg-passes1/pass-1-0.log', 'stats');
        $this->createFile('a/ffmpeg-passes1/other.log', 'other');

        $mapper = $this->createMapper();
        $args = $mapper->map(Tool::Ffmpeg, ['-y', '-i', $input, '-pass', '2', '-passlogfile', $prefix, $this->worker.'/a/out.mp4']);

        $jobPrefix = $args[6];
        $this->assertSame('stats', file_get_contents($jobPrefix.'-0.log'));
        $this->assertFileDoesNotExist(dirname($jobPrefix).'/other.log');

        file_put_contents($jobPrefix.'-0.log.mbtree', 'tree');
        $mapper->syncBack();
        $this->assertSame('tree', file_get_contents($prefix.'-0.log.mbtree'));
    }

    public function testPassLogPrefixMadeADirectoryByPhpFfmpeg(): void
    {
        $prefix = $this->worker.'/a/ffmpeg-passes1/pass-1';
        mkdir($prefix, 0700, true);
        $this->createFile('a/ffmpeg-passes1/pass-1-0.log', 'stats');

        $args = $this->createMapper()->map(Tool::Ffmpeg, ['-pass', '2', '-passlogfile', $prefix, $this->worker.'/a/out.mp4']);

        $this->assertSame('pass-1', basename($args[3]));
        $this->assertSame('stats', file_get_contents($args[3].'-0.log'));
    }

    public function testConcatListIsRewritten(): void
    {
        $clip1 = $this->createFile('a/clip 1.mp4', 'c1');
        $clip2 = $this->createFile('a/clip2.mp4', 'c2');
        $list = $this->createFile('a/ffmpeg-concat/ffmpeg.concat', 'file '.addcslashes($clip1, '\'"\\'."\0 ")."\nfile '".$clip2."'");

        $args = $this->createMapper()->map(Tool::Ffmpeg, ['-f', 'concat', '-safe', '0', '-i', $list, '-c', 'copy', $this->worker.'/a/out.mp4']);

        $this->assertSame(['-f', 'concat', '-safe', '0', '-i'], array_slice($args, 0, 5));
        $this->assertStringStartsWith($this->job.'/', $args[5]);
        $this->assertNotSame(fileinode($list), fileinode($args[5]));
        $lines = explode("\n", file_get_contents($args[5]));
        $this->assertCount(2, $lines);
        $this->assertMatchesRegularExpression("#^file '".preg_quote($this->job, '#')."/\d+/clip 1\.mp4'$#", $lines[0]);
        $this->assertSame(fileinode($clip1), fileinode(substr($lines[0], 6, -1)));
        $this->assertSame(fileinode($clip2), fileinode(substr($lines[1], 6, -1)));
    }

    public function testConcatListNamingAnotherFileIsRefused(): void
    {
        $list = $this->createFile('a/ffmpeg.concat', "file '/etc/passwd'");

        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Ffmpeg, ['-f', 'concat', '-safe', '0', '-i', $list, $this->worker.'/a/out.mp4']);
    }

    public function testFormatOptionOnlyAppliesToTheNextInput(): void
    {
        $input = $this->createFile('a/in.txt', 'not a list');

        $args = $this->createMapper()->map(Tool::Ffmpeg, ['-f', 'concat', '-i', $this->createFile('a/list', ''), '-i', $input, $this->worker.'/a/out.mp4']);

        $this->assertSame(fileinode($input), fileinode($args[5]));
    }

    public function testRelativePathInTheWorkerDirectory(): void
    {
        $input = $this->createFile('a/in.mp4', 'video');

        $mapper = new PathMapper($this->worker, $this->job, $this->worker.'/a');
        $args = $mapper->map(Tool::Ffmpeg, ['-i', 'in.mp4', '-c', 'copy', 'out.mp4']);

        $this->assertSame(fileinode($input), fileinode($args[1]));
        $this->assertSame(['-c', 'copy'], array_slice($args, 2, 2));
        // Not a file of the working directory (yet): an option value or an output, left as is
        $this->assertSame('out.mp4', $args[4]);
    }

    public function testRelativePathOutsideTheWorkerDirectoryIsLeftAsIs(): void
    {
        $this->createFile('a/in.mp4', 'video');

        $args = (new PathMapper($this->worker, $this->job, $this->root))->map(Tool::Ffmpeg, ['-i', 'worker/a/in.mp4']);

        $this->assertSame('worker/a/in.mp4', $args[1]);
    }

    public function testArgumentsWithoutPathAreKept(): void
    {
        $args = ['-loglevel', 'error', '-print_format', 'json', '-@', 'iptc2xmp.args', '-XMP-dc:Title=Hello', '/dev/null'];

        $this->assertSame($args, $this->createMapper()->map(Tool::Exiftool, $args));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnmappableArguments(): iterable
    {
        yield 'missing directory' => ['{worker}/missing/out.mp4'];
        yield 'relative segment' => ['{worker}/a/../../etc/passwd'];
        yield 'current segment' => ['{worker}/./a/in.mp4'];
        yield 'empty segment' => ['{worker}/a//in.mp4'];
        yield 'worker directory itself' => ['{worker}'];
        yield 'another directory with the same prefix' => ['{worker}-other/file'];
        yield 'file URL of a missing directory' => ['-env:UserInstallation=file://{worker}/missing/profile'];
    }

    #[DataProvider('provideUnmappableArguments')]
    public function testUnmappableArgumentIsRefused(string $argument): void
    {
        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Ffmpeg, [str_replace('{worker}', $this->worker, $argument)]);
    }

    public function testSymbolicLinkIsRefused(): void
    {
        symlink('/etc/passwd', $this->worker.'/a/link.jpg');

        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Exiftool, [$this->worker.'/a/link.jpg']);
    }

    public function testPathThroughSymbolicLinkIsRefused(): void
    {
        mkdir($this->root.'/outside');
        file_put_contents($this->root.'/outside/secret', 'secret');
        symlink($this->root.'/outside', $this->worker.'/a/dir');

        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Exiftool, [$this->worker.'/a/dir/secret']);
    }

    public function testExistingFileOutsideTheWorkerDirectoryIsRefused(): void
    {
        $this->expectException(UnmappablePathException::class);

        $this->createMapper()->map(Tool::Ffprobe, [__FILE__]);
    }

    public function testMirrorSkipsSymbolicLinks(): void
    {
        $this->createFile('a/profile/settings', 's');
        symlink('/etc/passwd', $this->worker.'/a/profile/passwd');

        $args = $this->createMapper()->map(Tool::Soffice, ['-env:UserInstallation=file://'.$this->worker.'/a/profile']);

        $mirror = substr($args[0], strlen('-env:UserInstallation=file://'));
        $this->assertFileExists($mirror.'/settings');
        $this->assertFalse(@lstat($mirror.'/passwd'));
    }

    private function createMapper(): PathMapper
    {
        return new PathMapper($this->worker, $this->job);
    }

    private function createFile(string $relativePath, string $content): string
    {
        $path = $this->worker.'/'.$relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $content);

        return $path;
    }
}
