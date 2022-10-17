<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

interface PermissionInterface
{
    const VIEW = 1;
    const CREATE = 2;
    const EDIT = 4;
    const DELETE = 8;
    const UNDELETE = 16;
    const OPERATOR = 32;
    const MASTER = 64;
    const OWNER = 128;
    const SHARE = 256;

    const PERMISSIONS = [
        'VIEW' => self::VIEW,
        'CREATE' => self::CREATE,
        'EDIT' => self::EDIT,
        'DELETE' => self::DELETE,
        'UNDELETE' => self::UNDELETE,
        'OPERATOR' => self::OPERATOR,
        'MASTER' => self::MASTER,
        'OWNER' => self::OWNER,
        'SHARE' => self::SHARE,
    ];
}
