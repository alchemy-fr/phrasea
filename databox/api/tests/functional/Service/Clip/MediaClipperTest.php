<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Clip;

use App\Model\MediaClip;
use App\Service\Clip\ClipException;
use App\Service\Clip\ClipResult;
use App\Service\Clip\MediaClipper;
use FFMpeg\FFProbe;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Cuts real media files (generated with ffmpeg) to clips.
 */
final class MediaClipperTest extends KernelTestCase
{
    private string $dir;
    private MediaClipper $clipper;

    protected function setUp(): void
    {
        parent::setUp();
        if (null === new ExecutableFinder()->find('ffmpeg') || null === new ExecutableFinder()->find('ffprobe')) {
            $this->markTestSkipped('ffmpeg is not available');
        }

        self::bootKernel();
        $this->clipper = self::getContainer()->get(MediaClipper::class);
        $this->dir = sys_get_temp_dir().'/'.uniqid('media-clipper-test');
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->clipper)) {
            $this->clipper->cleanUp();
        }
        if (isset($this->dir)) {
            array_map(unlink(...), glob($this->dir.'/*') ?: []);
            @rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function testVideoIsCutAndCropped(): void
    {
        $src = $this->generate('video.mp4', ['-f', 'lavfi', '-i', 'testsrc=duration=4:size=320x240:rate=25', '-pix_fmt', 'yuv420p']);

        $result = $this->clipper->apply($src, 'video/mp4', MediaClip::fromArray([
            'timeRanges' => [['s' => 1, 'e' => 2.5]],
            'crop' => ['x' => 0.25, 'y' => 0.25, 'w' => 0.5, 'h' => 0.5],
        ]));

        $this->assertTransformed($result, 'video/mp4', 'mp4');
        $stream = FFProbe::create()->streams($result->path)->videos()->first();
        $this->assertSame(160, $stream->getDimensions()->getWidth());
        $this->assertSame(120, $stream->getDimensions()->getHeight());
        $this->assertEqualsWithDelta(1.5, $this->duration($result->path), 0.2);
    }

    public function testVideoRangeOnly(): void
    {
        $src = $this->generate('video.webm', ['-f', 'lavfi', '-i', 'testsrc=duration=3:size=160x120:rate=25', '-c:v', 'libvpx', '-b:v', '200k']);

        $result = $this->clipper->apply($src, 'video/webm', MediaClip::fromArray([
            'timeRange' => ['s' => 0.5, 'e' => 10],
        ]));

        // The source container is kept, the end bounded to the duration
        $this->assertTransformed($result, 'video/webm', 'webm');
        $this->assertEqualsWithDelta(2.5, $this->duration($result->path), 0.3);
    }

    public function testAudioIsCut(): void
    {
        $src = $this->generate('sound.mp3', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=4']);

        $result = $this->clipper->apply($src, 'audio/mpeg', MediaClip::fromArray([
            'timeRanges' => [['s' => 1, 'e' => 3]],
            'crop' => ['x' => 0.25, 'y' => 0.25, 'w' => 0.5, 'h' => 0.5],
        ]));

        $this->assertTransformed($result, null, 'mp3');
        $this->assertEqualsWithDelta(2.0, $this->duration($result->path), 0.2);
    }

    public function testVideoRangesAreConcatenated(): void
    {
        $src = $this->generate('video.mp4', ['-f', 'lavfi', '-i', 'testsrc=duration=6:size=320x240:rate=25', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=6', '-pix_fmt', 'yuv420p', '-shortest']);

        $result = $this->clipper->apply($src, 'video/mp4', MediaClip::fromArray([
            'timeRanges' => [['s' => 0.5, 'e' => 1.5], ['s' => 3, 'e' => 5]],
            'crop' => ['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 0.5],
        ]));

        $this->assertTransformed($result, 'video/mp4', 'mp4');
        $this->assertEqualsWithDelta(3.0, $this->duration($result->path), 0.3);
        $streams = FFProbe::create()->streams($result->path);
        $this->assertSame(160, $streams->videos()->first()->getDimensions()->getWidth());
        $this->assertCount(1, $streams->audios());
    }

    public function testAudioRangesAreConcatenated(): void
    {
        $src = $this->generate('sound.mp3', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=6']);

        $result = $this->clipper->apply($src, 'audio/mpeg', MediaClip::fromArray([
            'timeRanges' => [['s' => 4, 'e' => 5], ['s' => 0, 'e' => 2]],
        ]));

        $this->assertTransformed($result, null, 'mp3');
        $this->assertEqualsWithDelta(3.0, $this->duration($result->path), 0.3);
    }

    public function testRangesAfterTheEndAreIgnored(): void
    {
        $src = $this->generate('sound.wav', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=3']);

        $result = $this->clipper->apply($src, 'audio/wav', MediaClip::fromArray([
            'timeRanges' => [['s' => 0, 'e' => 1], ['s' => 2, 'e' => 2.5], ['s' => 10, 'e' => 12]],
        ]));

        $this->assertTransformed($result, null, 'wav');
        $this->assertEqualsWithDelta(1.5, $this->duration($result->path), 0.2);

        // A single range left is cut as is
        $result = $this->clipper->apply($src, 'audio/wav', MediaClip::fromArray([
            'timeRanges' => [['s' => 1, 'e' => 2], ['s' => 10, 'e' => 12]],
        ]));
        $this->assertTransformed($result, null, 'wav');
        $this->assertEqualsWithDelta(1.0, $this->duration($result->path), 0.2);
    }

    public function testNoRangeWithinTheMediaFails(): void
    {
        $src = $this->generate('sound.mp3', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=2']);

        $this->expectException(ClipException::class);
        $this->clipper->apply($src, 'audio/mpeg', MediaClip::fromArray([
            'timeRanges' => [['s' => 5, 'e' => 6], ['s' => 10, 'e' => 12]],
        ]));
    }

    public function testCleanUpRemovesTheConcatenatedFile(): void
    {
        $src = $this->generate('sound.mp3', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=4']);

        $result = $this->clipper->apply($src, 'audio/mpeg', MediaClip::fromArray([
            'timeRanges' => [['s' => 0, 'e' => 1], ['s' => 2, 'e' => 3]],
        ]));
        $this->assertFileExists($result->path);

        $this->clipper->cleanUp();
        $this->assertFileDoesNotExist($result->path);
    }

    public function testImageIsCropped(): void
    {
        $src = $this->dir.'/image.png';
        $image = imagecreatetruecolor(200, 100);
        imagepng($image, $src);

        $result = $this->clipper->apply($src, 'image/png', MediaClip::fromArray([
            'timeRange' => ['s' => 1, 'e' => 3],
            'crop' => ['x' => 0.5, 'y' => 0, 'w' => 0.5, 'h' => 0.5],
        ]));

        $this->assertTransformed($result, 'image/png', 'png');
        $this->assertSame([100, 50], array_slice(getimagesize($result->path), 0, 2));
        $this->assertSame('photo.png', $result->renameFile('photo.png'));
    }

    public function testNothingToDo(): void
    {
        $src = $this->dir.'/doc.pdf';
        file_put_contents($src, '%PDF-1.4');
        $clip = MediaClip::fromArray(['crop' => ['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 0.5]]);

        foreach ([
            [$src, 'application/pdf', $clip],
            [$src, 'video/mp4', null],
            // A time range does not apply to an image
            [$src, 'image/png', MediaClip::fromArray(['timeRange' => ['s' => 0, 'e' => 1]])],
        ] as [$path, $type, $c]) {
            $result = $this->clipper->apply($path, $type, $c);
            $this->assertFalse($result->transformed, $type);
            $this->assertSame($path, $result->path);
            $this->assertSame($type, $result->mimeType);
            $this->assertSame('doc.pdf', $result->renameFile('doc.pdf'));
        }
    }

    private function assertTransformed(ClipResult $result, ?string $mimeType, string $extension): void
    {
        $this->assertTrue($result->transformed);
        $this->assertFileExists($result->path);
        if (null !== $mimeType) {
            $this->assertSame($mimeType, $result->mimeType);
        }
        $this->assertSame($extension, $result->extension);
        $this->assertSame('original.'.$extension, $result->renameFile('original.src'));
    }

    private function generate(string $name, array $input): string
    {
        $path = $this->dir.'/'.$name;
        $process = new Process(['ffmpeg', '-y', '-loglevel', 'error', ...$input, $path]);
        $process->mustRun();

        return $path;
    }

    private function duration(string $path): float
    {
        return (float) FFProbe::create()->format($path)->get('duration');
    }
}
