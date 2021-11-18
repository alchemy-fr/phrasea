<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

abstract class AbstractSecurityDataTransformer implements DataTransformerInterface
{
    private Security $security;

    protected function isGranted(string $attribute, object $object): bool
    {
        return $this->security->isGranted($attribute, $object);
    }

    /**
     * @required
     */
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function getStrictUser(): RemoteUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof RemoteUser) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    protected function getUser(): ?RemoteUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof RemoteUser) {
            return null;
        }

        return $user;
    }
}
