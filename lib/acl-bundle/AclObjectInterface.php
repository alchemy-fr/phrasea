<?php

declare(strict_types=1);

namespace Alchemy\AclBundle;

interface AclObjectInterface
{
    public function getId(): string;
    public function getAclOwnerId(): string;
}
