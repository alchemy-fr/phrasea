<?php

namespace App\Api\Model\Input;

use Alchemy\StorageBundle\Api\Dto\UploadInputTrait;

abstract class AbstractUploadInput extends AbstractOwnerIdInput
{
    use UploadInputTrait;
}
