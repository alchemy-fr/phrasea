<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MultipartUploadPartsOutput
{
    /**
     * @param array<int, string> $urls Presigned PUT URL of each part, keyed by part number
     */
    public function __construct(
        #[Groups(['upload:parts'])]
        public int $chunkSize,
        #[Groups(['upload:parts'])]
        public int $partCount,
        #[Groups(['upload:parts'])]
        public array $urls,
    ) {
    }
}
