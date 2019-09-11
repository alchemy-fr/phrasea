<?php

declare(strict_types=1);

namespace App\User\Import\ColReader;

use App\Entity\User;

class EnabledReader implements ColReaderInterface
{
    public function __invoke(string $str, User $user): void
    {
        $str = trim(strtolower($str));
        $enabled = $str && !in_array($str, ['non', 'no', 'n', '0'], true);

        $user->setEnabled($enabled);
    }

    public function supports(string $colName): bool
    {
        return 1 === preg_match('#^(actif|active|enabled?)$#i', $colName);
    }
}
