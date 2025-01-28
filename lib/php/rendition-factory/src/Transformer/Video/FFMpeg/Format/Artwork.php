<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use FFMpeg\Format\Audio\DefaultAudio;

class Artwork extends DefaultAudio
{
    public function getAvailableAudioCodecs()
    {
        return [];
    }
}
