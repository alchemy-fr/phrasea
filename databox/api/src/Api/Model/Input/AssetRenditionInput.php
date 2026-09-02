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
     * When "buildDefinition" is provided, this is the custom name of the dynamic rendition;
     * it must be unique among the asset's dynamic renditions (creating with an existing name is rejected).
     *
     * @var string|null
     */
    public $name;

    public ?bool $substituted = null;

    public $force;

    /**
     * Inline build specification (same YAML format as a rendition definition).
     * When provided, the rendition is built asynchronously from it (dynamic rendition, no stored definition).
     */
    public ?string $buildDefinition = null;

    /**
     * Whether to write the asset attribute metadata into the built file (dynamic renditions only).
     */
    public ?bool $writeMetadata = null;

    /**
     * Build from this rendition's file instead of the asset source file (dynamic renditions only).
     */
    public ?string $sourceRenditionId = null;
}
