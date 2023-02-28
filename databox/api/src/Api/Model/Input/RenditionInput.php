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
    public $definitionId = null;

    public ?string $assetId = null;

    /**
     * Rendition definition name. Or provide definitionId.
     *
     * @var string|null
     */
    public $name = null;

    /**
     * @var AssetSourceInput|null
     */
    public $source;

    /**
     * @var string|null
     */
    public $sourceFileId;
}
