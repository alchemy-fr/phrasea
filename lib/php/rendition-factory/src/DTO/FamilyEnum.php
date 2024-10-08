<?php

namespace Alchemy\RenditionFactory\DTO;

enum FamilyEnum: string
{
    case Image = 'image';
    case Animation = 'animation';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Unknown = 'unknown';
}
