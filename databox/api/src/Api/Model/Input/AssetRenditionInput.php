<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AssetRenditionInput extends AbstractUploadInput
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
    public $sourceFile;

    /**
     * @var string|null
     */
    public $sourceFileId;

    public $substituted;

    public $force;
}
