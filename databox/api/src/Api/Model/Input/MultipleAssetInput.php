<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class MultipleAssetInput extends AbstractOwnerIdInput
{
    /**
     * @var AssetInput[]
     */
    #[Assert\Valid]
    public ?array $assets = null;

    public ?bool $isStory = false;
}
