<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\WithOwnerIdInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends SecurityAwareTrait
 */
trait WithOwnerIdProcessorTrait
{
    /**
     * @template T of WithOwnerIdInterface
     *
     * @param T $data
     *
     * @return T
     */
    protected function processOwnerId(WithOwnerIdInterface $data): WithOwnerIdInterface
    {
        if (null === $data->getOwnerId()) {
            $user = $this->getUser();
            if (!$user instanceof JwtUser) {
                throw new BadRequestHttpException('You must provide "ownerId" as your access token is not associated to a user.');
            }

            $data->setOwnerId($user->getId());
        }

        return $data;
    }
}
