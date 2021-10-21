<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\WithOwnerIdInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends AbstractSecurityDataTransformer
 */
trait WithOwnerIdDataTransformerTrait
{
    protected function transformOwnerId(WithOwnerIdInterface $data, string $to, array $context = [])
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
