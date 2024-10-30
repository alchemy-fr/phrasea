<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\FormatInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class VideoTransformerBase
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] protected ServiceLocator $formats,
        protected ModuleOptionsResolver $optionsResolver,
    ) {
    }
}
