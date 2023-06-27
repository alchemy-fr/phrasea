<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class RenditionInput
{
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

    /**
     * @var AssetSourceInput|null
     */
    public $source;

    /**
     * @var string|null
     */
    public $sourceFileId;
}
