<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RoleChoiceHelper
{
    public static function getRoleChoices(AuthorizationCheckerInterface $authorizationChecker)
    {
        $choices = [
            'Users/Groups management' => 'ROLE_ADMIN_USERS',
            'Admin' => 'ROLE_ADMIN',
            'Developer / Ops' => 'ROLE_TECH',
            'Super Admin' => 'ROLE_SUPER_ADMIN',
        ];

        return array_filter($choices, fn (string $role): bool => $authorizationChecker->isGranted($role)
            || $authorizationChecker->isGranted('ROLE_SUPER_ADMIN'));
    }
}
