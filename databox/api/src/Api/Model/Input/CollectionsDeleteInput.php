<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class CollectionsDeleteInput
{
    use IdsInputTrait;
    public bool $hardDelete = false;
}
