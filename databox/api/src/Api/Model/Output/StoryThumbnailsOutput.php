<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class StoryThumbnailsOutput
{
    public function __construct(
        /**
         * @var array<int, string>
         */
        #[Groups(['_'])]
        public array $thumbnails,
    ) {
    }
}
