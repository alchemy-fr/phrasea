<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MultipartUploadPartUrlOutput
{
    public function __construct(
        #[Groups(['upload:part_url'])]
        public string $url,
    ) {
    }
}
