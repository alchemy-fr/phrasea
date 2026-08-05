<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** @uses ValidRenditionMetadataConstraintValidator */
#[\Attribute]
class ValidRenditionMetadataConstraint extends Constraint
{
    public string $invalidTagMessage = 'rendition_metadata.invalid_tag';
    public string $unknownTagMessage = 'rendition_metadata.unknown_tag';
    public string $notWritableTagMessage = 'rendition_metadata.not_writable_tag';
    public string $invalidValueMessage = 'rendition_metadata.invalid_value';

    #[\Override]
    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
