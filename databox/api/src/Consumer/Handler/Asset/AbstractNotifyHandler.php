<?php

namespace App\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Service\Asset\Attribute\AssetNameResolver;
use Doctrine\ORM\EntityManagerInterface;

abstract readonly class AbstractNotifyHandler
{
    public function __construct(
        protected AssetNameResolver $assetNameResolver,
        protected NotifierManager $notifierManager,
        protected EntityManagerInterface $em,
        protected UserRepository $userRepository,
    ) {
    }

    protected function getUsername(string $userId): string
    {
        $user = $this->userRepository->getUser($userId);

        return $user ? $user['username'] : 'Deleted User';
    }
}
