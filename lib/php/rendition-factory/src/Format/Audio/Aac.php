<?php

/*
 * This file replaces FFMpeg\Format\Audio\aac because alpine lacks libfdk
 */

namespace Alchemy\RenditionFactory\Format\Audio;

use FFMpeg\Format\Audio\DefaultAudio;

/**
 * The AAC audio format.
 */
class Aac extends DefaultAudio
{
    public function __construct()
    {
        $this->audioCodec = 'aac';
    }

    public function getAvailableAudioCodecs()
    {
        return ['aac'];
    }
}
