<?php

namespace App\Integration\Core\FileAnalyzer;

enum FileAnalyzerAssetActionEnum: string
{
    case QUARANTINE = 'quarantine';
    case MOVE_TO_TRASH = 'move_to_trash';
    case DELETE = 'delete';
}
