<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\Video;

class X264 extends \FFMpeg\Format\Video\X264
{
    protected $videoCodecs = [];

    public function __construct($audioCodec = 'aac', $videoCodec = 'libx264')
    {
        $this->videoCodecs = parent::getAvailableVideoCodecs();
        if (!in_array('copy', $this->videoCodecs)) {
            $this->videoCodecs[] = 'copy';
        }
        parent::__construct($audioCodec, $videoCodec);
    }

    public function getAvailableVideoCodecs()
    {
        return $this->videoCodecs;
    }
}
