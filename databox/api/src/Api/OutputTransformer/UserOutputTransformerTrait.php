<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Api\Model\Output\UserOutput;
use Symfony\Contracts\Service\Attribute\Required;

trait UserOutputTransformerTrait
{
    private UserRepositoryInterface $userRepository;

    protected function transformUser(?string $userId): ?UserOutput
    {
        if (null === $userId) {
            return null;
        }

        $output = new UserOutput();
        $output->id = $userId;

        $user = $this->userRepository->getUser($userId);
        if (null !== $user) {
            $output->username = $user['username'];
        }

        return $output;
    }

    #[Required]
    public function setUserRepository(UserRepositoryInterface $userRepository): void
    {
        $this->userRepository = $userRepository;
    }
}
