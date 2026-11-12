<?php

declare(strict_types=1);

namespace App\Security\Acl;

/**
 * Editing the data the targets merge into their commits.
 */
final readonly class TargetDataAclObject extends AbstractGlobalAclObject
{
    public const string OBJECT_KEY = 'target_params';
}
