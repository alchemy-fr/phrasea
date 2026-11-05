<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

/**
 * Time ranges (seconds, video/audio) and/or crop (relative 0..1 rectangle of the displayed
 * media, image/video): the region of interest of an asset (Asset::$roi) or the clip of a
 * basket item (BasketAsset::$context['clip']), stored as:
 *
 *     {"timeRanges": [{"s": 12.0, "e": 30.5}, {"s": 50, "e": 61.2}], "crop": {"x": 0.1, "y": 0.2, "w": 0.5, "h": 0.5}}
 *
 * The ranges are normalized: sorted, overlapping or contiguous ones merged, empty ones dropped.
 * The former single range format ({"timeRange": {"s": …, "e": …}}) is still read.
 *
 * The file itself is never cut: clients bound the playback and crop the display,
 * the Expose synchronization and the exports cut a temporary copy, the ranges
 * being concatenated in a single file.
 */
final readonly class MediaClip
{
    public const int MAX_TIME_RANGES = 50;

    /**
     * @param list<array{s: float, e: float}> $timeRanges
     */
    private function __construct(
        public array $timeRanges,
        public ?float $x,
        public ?float $y,
        public ?float $width,
        public ?float $height,
    ) {
    }

    public static function fromArray(?array $data): ?self
    {
        if (empty($data)) {
            return null;
        }

        $ranges = [];
        if (is_array($data['timeRanges'] ?? null)) {
            $ranges = $data['timeRanges'];
        }
        if (is_array($data['timeRange'] ?? null)) {
            $ranges[] = $data['timeRange'];
        }

        $crop = $data['crop'] ?? null;
        $hasCrop = is_array($crop) && isset($crop['x'], $crop['y'], $crop['w'], $crop['h']);

        return self::create(
            self::normalizeTimeRanges($ranges),
            $hasCrop ? (float) $crop['x'] : null,
            $hasCrop ? (float) $crop['y'] : null,
            $hasCrop ? (float) $crop['w'] : null,
            $hasCrop ? (float) $crop['h'] : null,
        );
    }

    /**
     * @param list<array{s: float, e: float}> $timeRanges normalized
     */
    private static function create(array $timeRanges, ?float $x, ?float $y, ?float $width, ?float $height): ?self
    {
        $clip = new self($timeRanges, $x, $y, $width, $height);

        return $clip->isEmpty() ? null : $clip;
    }

    /**
     * @return list<array{s: float, e: float}>
     */
    private static function normalizeTimeRanges(array $ranges): array
    {
        $valid = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || !isset($range['s'], $range['e']) || !is_numeric($range['s']) || !is_numeric($range['e'])) {
                continue;
            }
            $start = max(0.0, (float) $range['s']);
            $end = (float) $range['e'];
            if ($end > $start) {
                $valid[] = ['s' => $start, 'e' => $end];
            }
        }

        usort($valid, static fn (array $a, array $b): int => $a['s'] <=> $b['s'] ?: $a['e'] <=> $b['e']);

        $merged = [];
        foreach ($valid as $range) {
            $last = array_key_last($merged);
            if (null !== $last && $range['s'] <= $merged[$last]['e']) {
                $merged[$last]['e'] = max($merged[$last]['e'], $range['e']);
            } else {
                $merged[] = $range;
            }
        }

        return array_slice($merged, 0, self::MAX_TIME_RANGES);
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->hasTimeRange()) {
            $data['timeRanges'] = $this->timeRanges;
        }
        if ($this->hasCrop()) {
            $data['crop'] = [
                'x' => $this->x,
                'y' => $this->y,
                'w' => $this->width,
                'h' => $this->height,
            ];
        }

        return $data;
    }

    /**
     * Whether there is at least one time range.
     */
    public function hasTimeRange(): bool
    {
        return [] !== $this->timeRanges;
    }

    /**
     * @return list<array{s: float, e: float}>
     */
    public function getTimeRanges(): array
    {
        return $this->timeRanges;
    }

    public function hasCrop(): bool
    {
        return null !== $this->x && null !== $this->y && null !== $this->width && null !== $this->height;
    }

    public function isEmpty(): bool
    {
        return !$this->hasTimeRange() && !$this->hasCrop();
    }

    /**
     * @return float|null the total duration of the ranges, in seconds
     */
    public function getDuration(): ?float
    {
        if (!$this->hasTimeRange()) {
            return null;
        }

        return array_sum(array_map(static fn (array $range): float => $range['e'] - $range['s'], $this->timeRanges));
    }

    /**
     * The same crop with other time ranges (normalized).
     *
     * @param array<array{s: float|int, e: float|int}> $timeRanges
     */
    public function withTimeRanges(array $timeRanges): ?self
    {
        return self::create(self::normalizeTimeRanges($timeRanges), $this->x, $this->y, $this->width, $this->height);
    }

    public function withoutTimeRange(): ?self
    {
        return self::create([], $this->x, $this->y, $this->width, $this->height);
    }

    public function withoutCrop(): ?self
    {
        return self::create($this->timeRanges, null, null, null, null);
    }

    /**
     * The part of the clip that applies to a file of the given family:
     * the time ranges to videos and audios, the crop to images and videos.
     */
    public function forFamily(FamilyEnum $family): ?self
    {
        return match ($family) {
            FamilyEnum::Video => $this,
            FamilyEnum::Audio => $this->withoutCrop(),
            FamilyEnum::Image => $this->withoutTimeRange(),
            default => null,
        };
    }

    /**
     * Identifies the clip regardless of float noise (2 decimals for times, 4 for the rectangle).
     * A single range keeps the hash of the former single range clips: what was published
     * with them is not uploaded again.
     */
    public function getHash(): string
    {
        $data = [];
        if ($this->hasTimeRange()) {
            $ranges = array_map(static fn (array $range): array => [round($range['s'], 2), round($range['e'], 2)], $this->timeRanges);
            $data['t'] = 1 === count($ranges) ? $ranges[0] : $ranges;
        }
        if ($this->hasCrop()) {
            $data['c'] = [round($this->x, 4), round($this->y, 4), round($this->width, 4), round($this->height, 4)];
        }

        return substr(sha1(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)), 0, 16);
    }
}
