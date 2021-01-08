<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;

/**
 * @ApiResource(
 *     shortName="tag"
 * )
 */
class TagDTO extends AbstractUuidDTO
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     */
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
