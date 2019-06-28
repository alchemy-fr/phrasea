<?php

declare(strict_types=1);

namespace App\User\Import\ColReader;

use App\Entity\User;

interface ColReaderInterface
{
    public function __invoke(string $str, User $user): void;

    public function supports(string $colName): bool;
}
