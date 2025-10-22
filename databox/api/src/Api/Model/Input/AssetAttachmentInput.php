<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AssetAttachmentInput
{
    use UploadInputTrait;

    public ?string $assetId = null;

    public ?string $name = null;

    public ?int $priority = null;
}
