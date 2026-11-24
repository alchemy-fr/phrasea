<?php

declare(strict_types=1);

namespace App\Model;

enum QuarantineBatchActionEnum: string
{
    case Bypass = 'bypass';
    case Merge = 'merge';
    case Trash = 'trash';
    case Delete = 'delete';
}
