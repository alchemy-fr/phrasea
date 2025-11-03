<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AssetsDeleteInput
{
    use IdsInputTrait;

    public ?array $collections = null;

    public bool $hardDelete = false;
}
