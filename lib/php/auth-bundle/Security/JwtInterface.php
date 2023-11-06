<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

interface JwtInterface extends UserInterface
{
    public function getJwt(): string;

    public function getScopes(): array;
}
