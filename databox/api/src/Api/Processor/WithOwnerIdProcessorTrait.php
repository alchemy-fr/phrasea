<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\WithOwnerIdInterface;
use App\Util\SecurityAwareTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends SecurityAwareTrait
 */
trait WithOwnerIdProcessorTrait
{
    protected function processOwnerId(WithOwnerIdInterface $data): WithOwnerIdInterface
    {
        $user = $this->getUser();

        if (null === $data->getOwnerId()) {
            if (!$user instanceof JwtUser) {
                throw new BadRequestHttpException('You must provide "ownerId" as your access token is not associated to a user.');
            }

            $data->setOwnerId($user->getId());
        }

        return $data;
    }
}
