<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Video;

final class AudioCodecNormalizer
{
    /**
     * AAC encoders that are either non-free (libfdk_aac) or removed from ffmpeg
     * (libfaac, libvo_aacenc). Definitions imported from Phraseanet still reference
     * them; the native "aac" encoder is always available.
     */
    private const array AAC_ALIASES = ['libfdk_aac', 'libfaac', 'libvo_aacenc'];

    public static function normalize(string $codec): string
    {
        return in_array(strtolower($codec), self::AAC_ALIASES, true) ? 'aac' : $codec;
    }
}
