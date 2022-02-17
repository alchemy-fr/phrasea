<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Doctrine\Listener\SoftDeleteableListener;

class SoftDeleteToggler
{
    public function enable(): void
    {
        SoftDeleteableListener::enable();
    }

    public function disable(): void
    {
        SoftDeleteableListener::disable();
    }
}
