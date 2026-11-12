<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rendition;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\RenditionCreator;
use FFMpeg\FFProbe;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * ffmpeg and ffprobe only open local files: a forged video cannot make them read from the network.
 */
final class FFMpegProtocolWhitelistTest extends KernelTestCase
{
    private const string BUILD_CONFIG = <<<'YAML'
video:
    transformations:
        - module: ffmpeg
          options:
              format: video-mpeg4
              filters:
                  - {name: resize, width: 80, height: 60, mode: inset}
YAML;

    private string $dir;
    private RenditionCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        if (null === new ExecutableFinder()->find('ffmpeg') || null === new ExecutableFinder()->find('ffprobe')) {
            $this->markTestSkipped('ffmpeg is not available');
        }

        self::bootKernel();
        $this->creator = self::getContainer()->get(RenditionCreator::class);
        $this->dir = sys_get_temp_dir().'/'.uniqid('ffmpeg-protocol-test');
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->creator)) {
            $this->creator->cleanUp();
        }
        if (isset($this->dir)) {
            exec('rm -rf '.escapeshellarg($this->dir));
        }

        parent::tearDown();
    }

    public function testVideoIsTransformed(): void
    {
        $src = $this->generateVideo('video.mp4');

        $output = $this->createRendition($src);

        $this->assertFileExists($output);
        $stream = FFProbe::create()->streams($output)->videos()->first();
        $this->assertSame(80, $stream->getDimensions()->getWidth());
    }

    public function testRemotePlaylistIsNotOpened(): void
    {
        $src = $this->dir.'/playlist.m3u8';
        file_put_contents($src, <<<'M3U8'
#EXTM3U
#EXT-X-TARGETDURATION:1
#EXTINF:1.0,
http://127.0.0.1:9/x.ts
#EXT-X-ENDLIST

M3U8);

        $errors = $this->getRenditionErrors($src);

        $this->assertStringContainsString("Protocol 'http' not on whitelist 'file,pipe'", $errors);
    }

    public function testConcatScriptCannotReadAnotherFile(): void
    {
        mkdir($this->dir.'/other');
        $other = $this->generateVideo('other/secret.mp4');
        $src = $this->dir.'/script';
        file_put_contents($src, "ffconcat version 1.0\nfile '".$other."'\n");

        $errors = $this->getRenditionErrors($src);

        $this->assertStringContainsString(sprintf("Unsafe file name '%s'", $other), $errors);
    }

    private function createRendition(string $src): string
    {
        return $this->creator->createRendition(
            $src,
            'video/mp4',
            (new YamlLoader())->parse(self::BUILD_CONFIG),
            new CreateRenditionOptions(),
        )->getPath();
    }

    /**
     * @return string the messages of the exception chain
     */
    private function getRenditionErrors(string $src): string
    {
        try {
            $output = $this->createRendition($src);
        } catch (\Throwable $e) {
            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while (null !== $e = $e->getPrevious());

            return implode("\n", $messages);
        }

        $this->fail(sprintf('A rendition was created: %s', $output));
    }

    private function generateVideo(string $name): string
    {
        $path = $this->dir.'/'.$name;
        new Process([
            'ffmpeg', '-y', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=160x120:rate=10',
            '-pix_fmt', 'yuv420p', $path,
        ])->mustRun();

        return $path;
    }
}
