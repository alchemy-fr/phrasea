<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\WithOwnerIdInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends AbstractSecurityProcessor
 */
trait WithOwnerIdProcessorTrait
{
    protected function processOwnerId(WithOwnerIdInterface $data): WithOwnerIdInterface
    {
        $user = $this->getUser();

        if (null === $data->getOwnerId()) {
            if (!$user instanceof RemoteUser) {
                throw new BadRequestHttpException('You must provide "ownerId" as your access token is not associated to a user.');
            }

            $data->setOwnerId($user->getId());
        }

        return $data;
    }
}
