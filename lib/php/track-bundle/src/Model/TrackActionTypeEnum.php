<?php

namespace Alchemy\TrackBundle\Model;

enum TrackActionTypeEnum: int
{
    case UPDATE = 1;
    case CREATE = 2;
    case DELETE = 3;

    public static function getChoices(): array
    {
        return [
            'Update' => self::UPDATE,
            'Create' => self::CREATE,
            'Delete' => self::DELETE,
        ];
    }
}
