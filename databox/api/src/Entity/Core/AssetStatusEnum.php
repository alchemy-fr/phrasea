<?php

namespace App\Entity\Core;

enum AssetStatusEnum: int
{
    // Accepted/Published
    case Accepted = 0;

    // Pending for analysis
    case Pending = 1;

    case Quarantined = 2;
}
