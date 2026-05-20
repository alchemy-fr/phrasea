<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

class CollectionPrivacyInfoOutput
{
    public ?int $privacy = null;
    public ?int $computedPrivacy = null;
    public ?bool $canEditAssetPrivacy = null;
}
