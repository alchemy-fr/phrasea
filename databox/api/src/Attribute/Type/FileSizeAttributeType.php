<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class FileSizeAttributeType extends NumberAttributeType
{
    public const string NAME = 'filesize';
}
