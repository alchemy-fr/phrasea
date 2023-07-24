<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AclBundle\Model\AclUserInterface;

if (interface_exists(AclUserInterface::class)) {
    interface JwtUserInterface extends AclUserInterface
    {
    }
} else {
    interface JwtUserInterface
    {
    }
}
