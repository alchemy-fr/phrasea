<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Filter;

use FFMpeg\Filters\Frame\FrameFilterInterface;
use FFMpeg\Media\Frame;

class FrameQualityFilter implements FrameFilterInterface
{
    public function __construct(private int $quality = 0, private int $priority = 0)
    {
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    public function apply(Frame $frame): array
    {
        // 0...100 -> 31...1
        $compression = 31 - $this->quality * 0.3;

        return [
            '-qmin',
            '1',
            '-qscale:v',
            $compression,
        ];
    }
}
