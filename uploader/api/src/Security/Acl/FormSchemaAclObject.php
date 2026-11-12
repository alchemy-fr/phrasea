<?php

declare(strict_types=1);

namespace App\Security\Acl;

/**
 * Editing the forms of the targets.
 */
final readonly class FormSchemaAclObject extends AbstractGlobalAclObject
{
    public const string OBJECT_KEY = 'form_schema';
}
