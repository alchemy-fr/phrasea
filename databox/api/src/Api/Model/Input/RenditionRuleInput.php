<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\RenditionClass;

class RenditionRuleInput
{
    public ?string $userId = null;

    public ?string $groupId = null;

    public ?string $workspaceId = null;

    public ?string $collectionId = null;

    /**
     * @var RenditionClass[]
     */
    public ?array $allowed = null;
}
