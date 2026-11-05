<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use App\Model\MediaClip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaClipTest extends TestCase
{
    #[DataProvider('provideEmptyClips')]
    public function testEmptyClipIsNull(?array $data): void
    {
        $this->assertNull(MediaClip::fromArray($data));
    }

    public static function provideEmptyClips(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'null parts' => [['timeRanges' => null, 'timeRange' => null, 'crop' => null]];
        yield 'no range' => [['timeRanges' => []]];
        yield 'incomplete range' => [['timeRanges' => [['s' => 1]]]];
        yield 'incomplete legacy range' => [['timeRange' => ['s' => 1]]];
        yield 'empty range' => [['timeRanges' => [['s' => 2, 'e' => 2], ['s' => 3, 'e' => 1]]]];
        yield 'incomplete crop' => [['crop' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5]]];
    }

    public function testRoundTrip(): void
    {
        $data = [
            'timeRanges' => [['s' => 12, 'e' => 30.5], ['s' => 50, 'e' => 61.25]],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.25],
        ];
        $clip = MediaClip::fromArray($data);

        $this->assertTrue($clip->hasTimeRange());
        $this->assertTrue($clip->hasCrop());
        $this->assertFalse($clip->isEmpty());
        $this->assertSame(29.75, $clip->getDuration());
        $expected = [
            'timeRanges' => [['s' => 12.0, 'e' => 30.5], ['s' => 50.0, 'e' => 61.25]],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.25],
        ];
        $this->assertSame($expected, $clip->toArray());
        $this->assertSame($expected, MediaClip::fromArray($clip->toArray())->toArray());
    }

    public function testLegacySingleRangeIsRead(): void
    {
        $clip = MediaClip::fromArray([
            'timeRange' => ['s' => 12, 'e' => 30.5],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5],
        ]);

        $this->assertSame([['s' => 12.0, 'e' => 30.5]], $clip->getTimeRanges());
        $this->assertSame([
            'timeRanges' => [['s' => 12.0, 'e' => 30.5]],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5],
        ], $clip->toArray());
    }

    public function testRangesAreNormalized(): void
    {
        $clip = MediaClip::fromArray(['timeRanges' => [
            ['s' => 50, 'e' => 60],
            ['s' => 10, 'e' => 20],
            // overlapping
            ['s' => 15, 'e' => 25],
            // contiguous
            ['s' => 25, 'e' => 30],
            // inside another
            ['s' => 52, 'e' => 55],
            // empty
            ['s' => 40, 'e' => 40],
            ['s' => -5, 'e' => 2],
            'not a range',
        ]]);

        $this->assertSame([
            ['s' => 0.0, 'e' => 2.0],
            ['s' => 10.0, 'e' => 30.0],
            ['s' => 50.0, 'e' => 60.0],
        ], $clip->getTimeRanges());
        $this->assertSame(32.0, $clip->getDuration());
    }

    public function testRangesAreLimited(): void
    {
        $ranges = [];
        for ($i = 0; $i < MediaClip::MAX_TIME_RANGES + 10; ++$i) {
            $ranges[] = ['s' => $i * 10, 'e' => $i * 10 + 1];
        }

        $this->assertCount(MediaClip::MAX_TIME_RANGES, MediaClip::fromArray(['timeRanges' => $ranges])->getTimeRanges());
    }

    public function testPartialClips(): void
    {
        $range = MediaClip::fromArray(['timeRanges' => [['s' => 0, 'e' => 2]]]);
        $this->assertTrue($range->hasTimeRange());
        $this->assertFalse($range->hasCrop());
        $this->assertSame(['timeRanges' => [['s' => 0.0, 'e' => 2.0]]], $range->toArray());

        $crop = MediaClip::fromArray(['crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 0.5]]);
        $this->assertFalse($crop->hasTimeRange());
        $this->assertSame([], $crop->getTimeRanges());
        $this->assertNull($crop->getDuration());
        $this->assertSame(['crop' => ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 0.5]], $crop->toArray());
    }

    public function testWithTimeRanges(): void
    {
        $clip = MediaClip::fromArray([
            'timeRanges' => [['s' => 1, 'e' => 2], ['s' => 5, 'e' => 8]],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5],
        ]);

        $single = $clip->withTimeRanges([['s' => 5, 'e' => 8]]);
        $this->assertSame([['s' => 5.0, 'e' => 8.0]], $single->getTimeRanges());
        $this->assertTrue($single->hasCrop());
        $this->assertSame($clip->withoutTimeRange()->toArray(), $clip->withTimeRanges([])->toArray());

        $rangesOnly = $clip->withoutCrop();
        $this->assertNull($rangesOnly->withTimeRanges([]));
        $this->assertNull($rangesOnly->withoutTimeRange());
    }

    public function testForFamily(): void
    {
        $clip = MediaClip::fromArray([
            'timeRanges' => [['s' => 1, 'e' => 2]],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5],
        ]);

        $this->assertSame($clip, $clip->forFamily(FamilyEnum::Video));
        $this->assertSame(['timeRanges' => [['s' => 1.0, 'e' => 2.0]]], $clip->forFamily(FamilyEnum::Audio)->toArray());
        $this->assertSame(['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]], $clip->forFamily(FamilyEnum::Image)->toArray());
        $this->assertNull($clip->forFamily(FamilyEnum::Animation));
        $this->assertNull($clip->forFamily(FamilyEnum::Document));

        $rangeOnly = MediaClip::fromArray(['timeRanges' => [['s' => 1, 'e' => 2]]]);
        $this->assertNull($rangeOnly->forFamily(FamilyEnum::Image));
        $cropOnly = MediaClip::fromArray(['crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]]);
        $this->assertNull($cropOnly->forFamily(FamilyEnum::Audio));
    }

    public function testHashIgnoresFloatNoise(): void
    {
        $a = MediaClip::fromArray(['timeRanges' => [['s' => 12, 'e' => 30.5]], 'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]]);
        $b = MediaClip::fromArray(['timeRanges' => [['s' => 12.0001, 'e' => 30.4999]], 'crop' => ['x' => 0.100001, 'y' => 0.2, 'w' => 0.5, 'h' => 0.49999]]);
        $c = MediaClip::fromArray(['timeRanges' => [['s' => 12.1, 'e' => 30.5]], 'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]]);

        $this->assertSame($a->getHash(), $b->getHash());
        $this->assertNotSame($a->getHash(), $c->getHash());
        $this->assertNotSame($a->getHash(), $a->withoutCrop()->getHash());
        $this->assertNotSame($a->withoutCrop()->getHash(), $a->withoutTimeRange()->getHash());
    }

    /**
     * The hashes of the single range clips are those of the former single range model:
     * the items published to Expose with them are not uploaded again.
     */
    public function testSingleRangeHashIsStable(): void
    {
        $legacy = ['timeRange' => ['s' => 12, 'e' => 30.5]];
        $crop = ['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]];

        $this->assertSame('b33e52ad7106934c', MediaClip::fromArray($legacy)->getHash());
        $this->assertSame('b33e52ad7106934c', MediaClip::fromArray(['timeRanges' => [['s' => 12, 'e' => 30.5]]])->getHash());
        $this->assertSame('e0021cb22fa86c97', MediaClip::fromArray($legacy + $crop)->getHash());
        $this->assertSame('807f574883d86049', MediaClip::fromArray($crop)->getHash());
    }

    public function testSeveralRangesHash(): void
    {
        $two = MediaClip::fromArray(['timeRanges' => [['s' => 12, 'e' => 30.5], ['s' => 50, 'e' => 60]]]);
        $first = MediaClip::fromArray(['timeRanges' => [['s' => 12, 'e' => 30.5]]]);
        $reordered = MediaClip::fromArray(['timeRanges' => [['s' => 50, 'e' => 60], ['s' => 12, 'e' => 30.5]]]);

        $this->assertNotSame($first->getHash(), $two->getHash());
        $this->assertSame($two->getHash(), $reordered->getHash());
    }
}
