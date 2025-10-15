<?php

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PartInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public string|int|null $PartNumber = null;

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $ETag = null;
}
