<?php

namespace App\Service\Discussion;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;

final readonly class MentionExtractor
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function extractMentions(string $text): array
    {
        if (0 === preg_match_all('#@\[[^]]+]\(([^)]+)\)#', $text, $matches)) {
            return [];
        }

        return array_map(fn (array $u): string => $u['username'], $this->userRepository->getUsersByIds($matches[1]));
    }
}
