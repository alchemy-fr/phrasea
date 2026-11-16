<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rendition;

use Alchemy\RenditionFactory\Transformer\Video\FFMpegHelper;
use Alchemy\RenditionFactory\Transformer\Video\VideoSummaryTransformerModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The video modules run ffmpeg on files and definitions the workers do not trust:
 * neither may make them work without end.
 */
class VideoLimitsTest extends TestCase
{
    public function testClipCountIsCapped(): void
    {
        // A day of video, a clip every half second
        $this->assertSame(100, VideoSummaryTransformerModule::countClips(86400, 0.5, 100));
        $this->assertSame(12, VideoSummaryTransformerModule::countClips(60, 5, 100));
        $this->assertSame(0, VideoSummaryTransformerModule::countClips(-3, 5, 100));
    }

    public function testShortestAnnouncedDurationIsKept(): void
    {
        $this->assertSame(12.5, VideoSummaryTransformerModule::resolveInputDuration('10000000', '12.5'));
        $this->assertSame(12.5, VideoSummaryTransformerModule::resolveInputDuration('12.5', null));
        $this->assertSame(30.0, VideoSummaryTransformerModule::resolveInputDuration('N/A', 30));
    }

    #[DataProvider('getInvalidDurations')]
    public function testAbsurdDurationIsRefused(mixed $formatDuration, mixed $streamDuration): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VideoSummaryTransformerModule::resolveInputDuration($formatDuration, $streamDuration);
    }

    public static function getInvalidDurations(): iterable
    {
        yield 'above a day' => ['10000000', '9000000'];
        yield 'infinite' => ['1e400', null];
        yield 'unknown' => [null, 'N/A'];
        yield 'zero' => ['0', null];
    }

    public function testMaxClipsIsBounded(): void
    {
        $this->assertSame(VideoSummaryTransformerModule::DEFAULT_MAX_CLIPS, VideoSummaryTransformerModule::resolveMaxClips(null));
        $this->assertSame(20, VideoSummaryTransformerModule::resolveMaxClips('20'));

        $this->expectException(\InvalidArgumentException::class);
        VideoSummaryTransformerModule::resolveMaxClips(VideoSummaryTransformerModule::MAX_CLIPS_LIMIT + 1);
    }

    public function testTimeoutDefaultsToTheMaximum(): void
    {
        $this->assertSame(600, FFMpegHelper::resolveTimeout(null, 600));
        $this->assertSame(60, FFMpegHelper::resolveTimeout(60, 600));
        $this->assertSame(60, FFMpegHelper::resolveTimeout('60', 600));
    }

    #[DataProvider('getInvalidTimeouts')]
    public function testTimeoutAboveTheMaximumIsRefused(mixed $timeout): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FFMpegHelper::resolveTimeout($timeout, 600);
    }

    public static function getInvalidTimeouts(): iterable
    {
        yield 'above' => [601];
        yield 'zero' => [0];
        yield 'not a number' => ['forever'];
    }

    public function testTimeoutOfADefinitionIsValidated(): void
    {
        $this->assertSame(['timeout' => 60], $this->processTimeout(60));
        // Resolved when the rendition is built
        $this->assertSame(['timeout' => '{{ attr.timeout }}'], $this->processTimeout('{{ attr.timeout }}'));

        $this->expectException(InvalidConfigurationException::class);
        $this->processTimeout(7200);
    }

    private function processTimeout(mixed $timeout): array
    {
        $treeBuilder = new TreeBuilder('options');
        $treeBuilder->getRootNode()->children()->append(FFMpegHelper::createTimeoutNode(600));

        return new Processor()->process($treeBuilder->buildTree(), [['timeout' => $timeout]]);
    }
}
