<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Builds the "generate" renditions (contact sheets) of a story.
 */
#[MessengerMessage('p2')]
final readonly class BuildStoryContactSheet
{
    public function __construct(
        private string $storyAssetId,
        /**
         * Number of the retry, while story items do not have their rendition yet.
         */
        private int $attempt = 0,
    ) {
    }

    public function getStoryAssetId(): string
    {
        return $this->storyAssetId;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }
}
