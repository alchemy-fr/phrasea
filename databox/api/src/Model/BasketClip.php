<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

/**
 * Time range (seconds, video/audio) and/or crop (relative 0..1 rectangle, image/video)
 * applied to a basket item, stored in its context as:
 *
 *     {"timeRange": {"s": 12.0, "e": 30.5}, "crop": {"x": 0.1, "y": 0.2, "w": 0.5, "h": 0.5}}
 *
 * The file itself is never cut: clients bound the playback and crop the display,
 * the Expose synchronization and the exports cut a temporary copy.
 */
final readonly class BasketClip
{
    private function __construct(
        public ?float $start,
        public ?float $end,
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

        $range = $data['timeRange'] ?? null;
        $hasRange = is_array($range) && isset($range['s'], $range['e']);
        $crop = $data['crop'] ?? null;
        $hasCrop = is_array($crop) && isset($crop['x'], $crop['y'], $crop['w'], $crop['h']);

        if (!$hasRange && !$hasCrop) {
            return null;
        }

        return new self(
            $hasRange ? (float) $range['s'] : null,
            $hasRange ? (float) $range['e'] : null,
            $hasCrop ? (float) $crop['x'] : null,
            $hasCrop ? (float) $crop['y'] : null,
            $hasCrop ? (float) $crop['w'] : null,
            $hasCrop ? (float) $crop['h'] : null,
        );
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->hasTimeRange()) {
            $data['timeRange'] = [
                's' => $this->start,
                'e' => $this->end,
            ];
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

    public function hasTimeRange(): bool
    {
        return null !== $this->start && null !== $this->end;
    }

    public function hasCrop(): bool
    {
        return null !== $this->x && null !== $this->y && null !== $this->width && null !== $this->height;
    }

    public function getDuration(): ?float
    {
        return $this->hasTimeRange() ? $this->end - $this->start : null;
    }

    public function withoutTimeRange(): ?self
    {
        if (!$this->hasCrop()) {
            return null;
        }

        return new self(null, null, $this->x, $this->y, $this->width, $this->height);
    }

    public function withoutCrop(): ?self
    {
        if (!$this->hasTimeRange()) {
            return null;
        }

        return new self($this->start, $this->end, null, null, null, null);
    }

    /**
     * The part of the clip that applies to a file of the given family:
     * the time range to videos and audios, the crop to images and videos.
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
     */
    public function getHash(): string
    {
        $data = [];
        if ($this->hasTimeRange()) {
            $data['t'] = [round($this->start, 2), round($this->end, 2)];
        }
        if ($this->hasCrop()) {
            $data['c'] = [round($this->x, 4), round($this->y, 4), round($this->width, 4), round($this->height, 4)];
        }

        return substr(sha1(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)), 0, 16);
    }
}
