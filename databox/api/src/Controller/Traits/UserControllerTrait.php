<?php

declare(strict_types=1);

namespace App\Controller\Traits;

use Alchemy\AuthBundle\Security\JwtUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait UserControllerTrait
{
    public function getStrictUser(): JwtUser
    {
        $user = $this->getUser();
        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException(sprintf('No user or not a %s', JwtUser::class));
        }

        return $user;
    }
}
