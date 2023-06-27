<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class AbstractSecurityDataTransformer implements DataTransformerInterface
{
    private Security $security;

    /**
     * @param string|int $attribute
     */
    protected function isGranted($attribute, object $object): bool
    {
        if (null === $this->security->getToken()) {
            return false;
        }

        return $this->security->isGranted($attribute, $object);
    }

    protected function getTokenId(): string
    {
        if (is_object($this->security->getToken())) {
            return (string) spl_object_id($this->security->getToken());
        }

        return 'no_token';
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function getStrictUser(): RemoteUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof RemoteUser) {
            throw new AccessDeniedHttpException('User must be authenticated');
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
