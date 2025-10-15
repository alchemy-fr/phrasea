<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AssetRenditionInput
{
    use UploadInputTrait;

    /**
     * Rendition definition ID. Or provide name.
     *
     * @var string|null
     */
    public $definitionId;

    public ?string $assetId = null;

    /**
     * Rendition definition name. Or provide definitionId.
     *
     * @var string|null
     */
    public $name;

    public ?bool $substituted = null;

    public $force;
}
