<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;

trait UpdatedAtDTOTrait
{
    /**
     * @ApiProperty()
     * @Groups({"dates"})
     */
    protected DateTimeInterface $updatedAt;

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
