<?php

namespace App\Api\Model\Input;

use Alchemy\StorageBundle\Api\Dto\UploadInputTrait as BaseUploadInputTrait;
use Symfony\Component\Validator\Constraints as Assert;

trait UploadInputTrait
{
    use BaseUploadInputTrait;

    /**
     * @var FileSourceInput|null
     */
    #[Assert\Valid]
    public $sourceFile;

    public ?string $sourceFileId = null;
}
