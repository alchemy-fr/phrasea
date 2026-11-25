<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class EntityHierarchyImportInput
{
    /**
     * CSV with a header row: entity_external_id, parent_external_id (empty: top level); the children
     * of a parent take the order of the rows.
     * An entity without external ID may be referenced by its ID.
     */
    #[Assert\NotBlank]
    public ?string $data = null;

    /**
     * Only report what the import would do.
     */
    public bool $dryRun = false;
}
