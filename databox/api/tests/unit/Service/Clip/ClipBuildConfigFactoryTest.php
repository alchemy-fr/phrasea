<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clip;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use App\Model\BasketClip;
use App\Service\Clip\ClipBuildConfigFactory;
use App\Service\Clip\VideoProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClipBuildConfigFactoryTest extends TestCase
{
    private const array RANGE = ['timeRange' => ['s' => 12, 'e' => 30.5]];
    private const array CROP = ['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]];

    public function testVideoClipStartsWithPreClipThenCrops(): void
    {
        $options = $this->createOptions(FamilyEnum::Video, 'video/mp4', self::RANGE + self::CROP, new VideoProbe(1920, 1080, duration: 60.0), 'ffmpeg');

        $this->assertSame('video-mpeg4', $options['format']);
        $this->assertSame('mp4', $options['extension']);
        $this->assertSame(0, $options['video_kilobitrate']);
        $this->assertArrayNotHasKey('passes', $options);
        $this->assertSame([
            [
                'name' => 'pre_clip',
                'start' => 12.0,
                'duration' => 18.5,
            ],
            [
                'name' => 'crop',
                'x' => 192,
                'y' => 216,
                'width' => 960,
                'height' => 540,
            ],
        ], $options['filters']);
    }

    public function testVideoRangeIsBoundedToTheDuration(): void
    {
        $options = $this->createOptions(FamilyEnum::Video, 'video/mp4', self::RANGE, new VideoProbe(640, 360, duration: 20.0), 'ffmpeg');

        $this->assertSame([
            [
                'name' => 'pre_clip',
                'start' => 12.0,
                'duration' => 8.0,
            ],
        ], $options['filters']);
    }

    public function testVideoRangeAfterTheEndIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createOptions(FamilyEnum::Video, 'video/mp4', self::RANGE, new VideoProbe(640, 360, duration: 10.0), 'ffmpeg');
    }

    public function testVideoCropPixelsAreEvenAndBounded(): void
    {
        $options = $this->createOptions(FamilyEnum::Video, 'video/mp4', [
            'crop' => ['x' => 0.33, 'y' => 0.5, 'w' => 0.67, 'h' => 0.5],
        ], new VideoProbe(1001, 3), 'ffmpeg');

        $crop = $options['filters'][0];
        $this->assertSame('crop', $crop['name']);
        foreach (['x', 'y', 'width', 'height'] as $key) {
            $this->assertSame(0, $crop[$key] % 2, $key);
        }
        $this->assertLessThanOrEqual(1001, $crop['x'] + $crop['width']);
        $this->assertLessThanOrEqual(3, $crop['y'] + $crop['height']);
        $this->assertSame(2, $crop['height']);
        $this->assertSame(0, $crop['y']);

        // A tiny crop is at least 2px
        $options = $this->createOptions(FamilyEnum::Video, 'video/mp4', [
            'crop' => ['x' => 0.999, 'y' => 0.999, 'w' => 0.0001, 'h' => 0.0001],
        ], new VideoProbe(1000, 1000), 'ffmpeg');
        $crop = $options['filters'][0];
        $this->assertSame(2, $crop['width']);
        $this->assertSame(2, $crop['height']);
        $this->assertSame(998, $crop['x']);
        $this->assertSame(998, $crop['y']);
    }

    #[DataProvider('provideRotations')]
    public function testVideoCropUsesTheDisplayedSize(int $rotation, int $expectedWidth, int $expectedHeight): void
    {
        $options = $this->createOptions(FamilyEnum::Video, 'video/mp4', [
            'crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
        ], new VideoProbe(1920, 1080, $rotation), 'ffmpeg');

        $this->assertSame($expectedWidth, $options['filters'][0]['width']);
        $this->assertSame($expectedHeight, $options['filters'][0]['height']);
    }

    public static function provideRotations(): iterable
    {
        yield 'none' => [0, 1920, 1080];
        yield '90' => [90, 1080, 1920];
        yield '-90' => [-90, 1080, 1920];
        yield '270' => [270, 1080, 1920];
        yield '180' => [180, 1920, 1080];
    }

    public function testVideoCropRequiresTheProbe(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createOptions(FamilyEnum::Video, 'video/mp4', self::CROP, null, 'ffmpeg');
    }

    #[DataProvider('provideVideoFormats')]
    public function testVideoKeepsTheContainer(string $mimeType, string $format, string $extension, ?int $kbps, int $expectedKbps): void
    {
        $options = $this->createOptions(FamilyEnum::Video, $mimeType, self::RANGE, new VideoProbe(640, 360, kbps: $kbps), 'ffmpeg');

        $this->assertSame($format, $options['format']);
        $this->assertSame($extension, $options['extension']);
        $this->assertSame($expectedKbps, $options['video_kilobitrate']);
    }

    public static function provideVideoFormats(): iterable
    {
        yield 'mp4' => ['video/mp4', 'video-mpeg4', 'mp4', 3000, 0];
        yield 'm4v' => ['video/x-m4v', 'video-mpeg4', 'mp4', 3000, 0];
        yield 'quicktime' => ['video/quicktime', 'video-quicktime', 'mov', 3000, 0];
        yield 'matroska' => ['video/x-matroska', 'video-mkv', 'mkv', 3000, 0];
        yield 'webm' => ['video/webm', 'video-webm', 'webm', 3000, 3000];
        yield 'webm low bitrate' => ['video/webm', 'video-webm', 'webm', 100, ClipBuildConfigFactory::MIN_VIDEO_KILOBITRATE];
        yield 'ogg' => ['video/ogg', 'video-ogg', 'ogv', 800, 800];
        yield 'other' => ['video/x-msvideo', 'video-mpeg4', 'mp4', null, 0];
    }

    public function testAudioClipIgnoresTheCrop(): void
    {
        $options = $this->createOptions(FamilyEnum::Audio, 'audio/mpeg', self::RANGE + self::CROP, null, 'ffmpeg');

        $this->assertSame('audio-mp3', $options['format']);
        $this->assertSame([
            [
                'name' => 'clip',
                'start' => 12.0,
                'duration' => 18.5,
            ],
        ], $options['filters']);
    }

    #[DataProvider('provideAudioFormats')]
    public function testAudioFormats(string $mimeType, string $format, string $extension): void
    {
        $options = $this->createOptions(FamilyEnum::Audio, $mimeType, self::RANGE, null, 'ffmpeg');

        $this->assertSame($format, $options['format']);
        $this->assertSame($extension, $options['extension']);
    }

    public static function provideAudioFormats(): iterable
    {
        yield 'mp3' => ['audio/mpeg', 'audio-mp3', 'mp3'];
        yield 'wav' => ['audio/x-wav', 'audio-wav', 'wav'];
        yield 'aac' => ['audio/aac', 'audio-aac', 'aac'];
        yield 'm4a' => ['audio/mp4', 'audio-aac', 'm4a'];
        yield 'ogg' => ['audio/ogg', 'audio-ogg', 'ogg'];
        yield 'other' => ['audio/flac', 'audio-mp3', 'mp3'];
    }

    public function testCropOnlyAudioIsANoop(): void
    {
        $this->assertNull(new ClipBuildConfigFactory()->create(FamilyEnum::Audio, 'audio/mpeg', BasketClip::fromArray(self::CROP)));
    }

    public function testImageClipIgnoresTheRange(): void
    {
        $options = $this->createOptions(FamilyEnum::Image, 'image/png', self::RANGE + self::CROP, null, 'imagine');

        $this->assertSame('png', $options['format']);
        $this->assertSame(ClipBuildConfigFactory::IMAGE_QUALITY, $options['quality']);
        $this->assertSame(['auto_rotate', 'relative_crop'], array_keys($options['filters']));
        $this->assertSame([
            'x' => 0.1,
            'y' => 0.2,
            'width' => 0.5,
            'height' => 0.5,
        ], $options['filters']['relative_crop']);
    }

    public function testImageWithoutWebFormatIsConvertedToJpeg(): void
    {
        $options = $this->createOptions(FamilyEnum::Image, 'image/tiff', self::CROP, null, 'imagine');

        $this->assertSame('jpeg', $options['format']);
    }

    public function testRangeOnlyImageIsANoop(): void
    {
        $this->assertNull(new ClipBuildConfigFactory()->create(FamilyEnum::Image, 'image/jpeg', BasketClip::fromArray(self::RANGE)));
    }

    #[DataProvider('provideNoopFamilies')]
    public function testOtherFamiliesAreANoop(FamilyEnum $family): void
    {
        $this->assertNull(new ClipBuildConfigFactory()->create($family, 'application/octet-stream', BasketClip::fromArray(self::RANGE + self::CROP)));
    }

    public static function provideNoopFamilies(): iterable
    {
        yield 'animation' => [FamilyEnum::Animation];
        yield 'document' => [FamilyEnum::Document];
        yield 'unknown' => [FamilyEnum::Unknown];
    }

    public function testNoClipIsANoop(): void
    {
        $this->assertNull(new ClipBuildConfigFactory()->create(FamilyEnum::Video, 'video/mp4', null));
        $this->assertNull(new ClipBuildConfigFactory()->create(FamilyEnum::Video, 'video/mp4', BasketClip::fromArray([])));
    }

    private function createOptions(FamilyEnum $family, string $mimeType, array $clip, ?VideoProbe $probe, string $expectedModule): array
    {
        $buildConfig = new ClipBuildConfigFactory()->create($family, $mimeType, BasketClip::fromArray($clip), $probe);
        $this->assertInstanceOf(BuildConfig::class, $buildConfig);

        $familyConfig = $buildConfig->getFamily($family);
        $this->assertNotNull($familyConfig);
        $transformations = $familyConfig->getEnabledTransformations();
        $this->assertCount(1, $transformations);
        $this->assertSame($expectedModule, $transformations[0]->getModule());

        return $transformations[0]->getOptions();
    }
}
