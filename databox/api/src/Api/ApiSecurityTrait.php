<?php

declare(strict_types=1);

namespace App\Api;

use Alchemy\AuthBundle\Model\RemoteUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;

trait ApiSecurityTrait
{
    private Security $security;

    protected function isGranted(string $attribute, object $object): bool
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

    #[Required]
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
