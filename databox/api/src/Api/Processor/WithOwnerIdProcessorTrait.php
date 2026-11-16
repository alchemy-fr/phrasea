<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Input\AbstractOwnerIdInput;
use App\Entity\WithOwnerIdInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    /**
     * The "ownerId" of the input: a user may only give their own ID, unless admin.
     * A token without user (OAuth client) must give it; without any token, the
     * creation itself is refused afterwards.
     */
    protected function getInputOwnerId(AbstractOwnerIdInput $input): ?string
    {
        $ownerId = $input->getOwnerId();
        if (null === $ownerId || '' === $ownerId) {
            return null;
        }

        $user = $this->getUser();
        if ($user instanceof JwtUser && $ownerId !== $user->getId() && !$this->isAdmin()) {
            throw new AccessDeniedHttpException('Only an admin can set "ownerId" to another user');
        }

        return $ownerId;
    }
}
