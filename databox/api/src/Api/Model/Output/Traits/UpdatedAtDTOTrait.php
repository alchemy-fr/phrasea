<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

trait UpdatedAtDTOTrait
{
    #[Groups(['dates'])]
    #[ApiProperty]
    protected \DateTimeInterface $updatedAt;

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
