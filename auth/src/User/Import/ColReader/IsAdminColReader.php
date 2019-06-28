<?php

declare(strict_types=1);

namespace App\User\Import\ColReader;

use App\Entity\User;

class IsAdminColReader implements ColReaderInterface
{
    public function __invoke(string $str, User $user): void
    {
        $str = trim(strtolower($str));

        if ($str && !in_array($str, ['non', 'no', 'n', '0'], true)) {
            $user->setRoles(['ROLE_ADMIN']);
        }
    }

    public function supports(string $colName): bool
    {
        return 1 === preg_match('#^(?:is|est)?\s*admin(?:istrat(?:eu|o)r)?$#i', $colName);
    }
}
