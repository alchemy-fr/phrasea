<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Serializer;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;

class AceSerializer
{
    public function serialize(AccessControlEntryInterface $ace): array
    {
        return [
            'userType' => array_search($ace->getUserType(), AccessControlEntryInterface::USER_TYPES, true),
            'userId' => $ace->getUserId(),
            'objectType' => $ace->getObjectType(),
            'objectId' => $ace->getObjectId(),
            'mask' => $ace->getMask(),
        ];
    }
}
