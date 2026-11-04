<?php

declare(strict_types=1);

namespace App\Service\Clip;

/**
 * What the clip of a video needs to know about its source.
 */
final readonly class VideoProbe
{
    public function __construct(
        public int $width,
        public int $height,
        /** Rotation of the display matrix, in degrees */
        public int $rotation = 0,
        public ?int $kbps = null,
        /** In seconds */
        public ?float $duration = null,
    ) {
    }

    /**
     * Size of the frames once rotated, as displayed (and as encoded by ffmpeg, which auto-rotates).
     *
     * @return array{int, int}
     */
    public function getDisplaySize(): array
    {
        if (0 !== abs($this->rotation) % 180 && 0 === abs($this->rotation) % 90) {
            return [$this->height, $this->width];
        }

        return [$this->width, $this->height];
    }
}
