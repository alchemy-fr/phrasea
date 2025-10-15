<?php

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class MultipartUploadInput
{
    /**
     * List of uploaded parts.
     *
     * @var PartInput[]
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\Valid]
    public ?array $parts;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?string $uploadId = null;
}
