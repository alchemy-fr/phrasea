<?php

declare(strict_types=1);

namespace App\Controller\Traits;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait UserControllerTrait
{
    public function getStrictUser(): RemoteUser
    {
        $user = $this->getUser();
        if (!$user instanceof RemoteUser) {
            throw new AccessDeniedHttpException(sprintf('No user or not a %s', RemoteUser::class));
        }

        return $user;
    }
}
