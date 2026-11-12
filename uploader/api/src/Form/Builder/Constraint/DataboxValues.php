<?php

declare(strict_types=1);

namespace App\Form\Builder\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * The value(s) must be entities of the list, or tags of the workspace, in Databox.
 */
final class DataboxValues extends Constraint
{
    public string $message = 'The value {{ value }} is not a valid choice.';
    public string $unavailableMessage = 'The values could not be checked against Databox.';

    public function __construct(
        public ?string $entityListId = null,
        public ?string $workspaceId = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
