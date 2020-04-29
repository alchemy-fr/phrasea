<?php

declare(strict_types=1);

namespace Alchemy\AclBundle;

interface UserInterface
{
    public function getId(): string;

    /**
     * @return string[]
     */
    public function getGroupIds(): array;
}
