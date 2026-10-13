<?php

declare(strict_types=1);

namespace App\Api\Upload;

final readonly class ResolvedUpload
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public string $originalName,
        public int $size,
        // Signed PUT URL the client must upload the file to, when the request only described the file.
        public ?string $uploadUrl = null,
    ) {
    }
}
