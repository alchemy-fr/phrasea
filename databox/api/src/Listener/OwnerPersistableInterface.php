<?php

declare(strict_types=1);

namespace App\Listener;

use App\Entity\WithOwnerIdInterface;

interface OwnerPersistableInterface extends WithOwnerIdInterface
{
}
