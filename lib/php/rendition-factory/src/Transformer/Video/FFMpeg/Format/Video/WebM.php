<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\Video;

class WebM extends \FFMpeg\Format\Video\WebM
{
    protected $videoCodecs = [];

    public function __construct($audioCodec = 'libvorbis', $videoCodec = 'libvpx')
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
