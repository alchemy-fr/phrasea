<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use App\Model\BasketClip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BasketClipTest extends TestCase
{
    #[DataProvider('provideEmptyClips')]
    public function testEmptyClipIsNull(?array $data): void
    {
        $this->assertNull(BasketClip::fromArray($data));
    }

    public static function provideEmptyClips(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'null parts' => [['timeRange' => null, 'crop' => null]];
        yield 'incomplete range' => [['timeRange' => ['s' => 1]]];
        yield 'incomplete crop' => [['crop' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5]]];
    }

    public function testRoundTrip(): void
    {
        $data = [
            'timeRange' => ['s' => 12, 'e' => 30.5],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.25],
        ];
        $clip = BasketClip::fromArray($data);

        $this->assertTrue($clip->hasTimeRange());
        $this->assertTrue($clip->hasCrop());
        $this->assertSame(18.5, $clip->getDuration());
        $this->assertSame([
            'timeRange' => ['s' => 12.0, 'e' => 30.5],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.25],
        ], $clip->toArray());
    }

    public function testPartialClips(): void
    {
        $range = BasketClip::fromArray(['timeRange' => ['s' => 0, 'e' => 2]]);
        $this->assertTrue($range->hasTimeRange());
        $this->assertFalse($range->hasCrop());
        $this->assertSame(['timeRange' => ['s' => 0.0, 'e' => 2.0]], $range->toArray());

        $crop = BasketClip::fromArray(['crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 0.5]]);
        $this->assertFalse($crop->hasTimeRange());
        $this->assertNull($crop->getDuration());
        $this->assertSame(['crop' => ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 0.5]], $crop->toArray());
    }

    public function testForFamily(): void
    {
        $clip = BasketClip::fromArray([
            'timeRange' => ['s' => 1, 'e' => 2],
            'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5],
        ]);

        $this->assertSame($clip, $clip->forFamily(FamilyEnum::Video));
        $this->assertSame(['timeRange' => ['s' => 1.0, 'e' => 2.0]], $clip->forFamily(FamilyEnum::Audio)->toArray());
        $this->assertSame(['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]], $clip->forFamily(FamilyEnum::Image)->toArray());
        $this->assertNull($clip->forFamily(FamilyEnum::Animation));
        $this->assertNull($clip->forFamily(FamilyEnum::Document));

        $rangeOnly = BasketClip::fromArray(['timeRange' => ['s' => 1, 'e' => 2]]);
        $this->assertNull($rangeOnly->forFamily(FamilyEnum::Image));
        $cropOnly = BasketClip::fromArray(['crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]]);
        $this->assertNull($cropOnly->forFamily(FamilyEnum::Audio));
    }

    public function testHashIgnoresFloatNoise(): void
    {
        $a = BasketClip::fromArray(['timeRange' => ['s' => 12, 'e' => 30.5], 'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]]);
        $b = BasketClip::fromArray(['timeRange' => ['s' => 12.0001, 'e' => 30.4999], 'crop' => ['x' => 0.100001, 'y' => 0.2, 'w' => 0.5, 'h' => 0.49999]]);
        $c = BasketClip::fromArray(['timeRange' => ['s' => 12.1, 'e' => 30.5], 'crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]]);

        $this->assertSame($a->getHash(), $b->getHash());
        $this->assertNotSame($a->getHash(), $c->getHash());
        $this->assertNotSame($a->getHash(), $a->withoutCrop()->getHash());
        $this->assertNotSame($a->withoutCrop()->getHash(), $a->withoutTimeRange()->getHash());
    }
}
