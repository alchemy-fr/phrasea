<?php

declare(strict_types=1);

namespace App\Form\Builder;

enum FieldSource: string
{
    /** A Databox attribute definition: the key is its slug */
    case Attribute = 'attribute';
    /** A value Databox (or the uploader) handles itself: name, tags, story, notification */
    case Builtin = 'builtin';
    /** A free key, for targets other than Databox */
    case Custom = 'custom';
    /** A section title or a text: never submitted */
    case Layout = 'layout';
}
