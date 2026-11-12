<?php

declare(strict_types=1);

namespace App\Security\Acl;

use Alchemy\AclBundle\AclObjectInterface;

/**
 * A permission that only exists on the whole type (ACEs without object id): the subject of the ACL voter.
 */
abstract readonly class AbstractGlobalAclObject implements AclObjectInterface
{
    /**
     * Never stored as the id of an ACE: only the ACEs on every object of the type match.
     */
    private const string NO_OBJECT_ID = '00000000-0000-0000-0000-000000000000';

    public function getId(): string
    {
        return self::NO_OBJECT_ID;
    }

    public function getAclOwnerId(): string
    {
        return '';
    }
}
