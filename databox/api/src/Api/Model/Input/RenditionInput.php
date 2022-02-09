<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class RenditionInput
{
    public ?string $definition = null;

    /**
     * @var AssetSourceInput
     */
    public $source;
}
