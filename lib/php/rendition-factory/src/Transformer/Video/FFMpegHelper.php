<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use FFMpeg\FFMpeg;

class FFMpegHelper
{
    public static function createFFMpeg(array $options, TransformationContextInterface $context): FFMpeg
    {
        $ffmpegOptions = [];
        if ($timeout = $options['timeout'] ?? 3600) {
            if (!is_int($timeout)) {
                throw new \InvalidArgumentException('Invalid timeout');
            }
            $ffmpegOptions['timeout'] = $timeout;
        }
        if ($threads = $options['threads'] ?? null) {
            if (!is_int($threads) || $threads < 1) {
                throw new \InvalidArgumentException('Invalid threads count');
            }
            $ffmpegOptions['ffmpeg.threads'] = $threads;
        }

        return FFMpeg::create($ffmpegOptions, $context->getLogger());
    }
}
