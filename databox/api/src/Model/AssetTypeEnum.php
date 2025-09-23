<?php

namespace App\Model;

enum AssetTypeEnum: int
{
    case Asset = 1;
    case Story = 2;
    case Both = 3;
}
