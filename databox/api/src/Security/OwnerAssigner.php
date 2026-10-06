<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\WithOwnerIdInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class OwnerAssigner
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * Sets the current user as owner when the object has none yet.
     */
    public function assignIfMissing(WithOwnerIdInterface $object): void
    {
        if (null !== $object->getOwnerId()) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof JwtUser) {
            $object->setOwnerId($user->getId());
        }
    }
}
