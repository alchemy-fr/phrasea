<?php

namespace App\Service;

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

        $mentions = [];
        foreach ($matches[1] as $match) {
            $user = $this->userRepository->getUser($match);
            if ($user) {
                $mentions[$user['id']] = $user['username'];
            }
        }

        return $mentions;
    }
}
