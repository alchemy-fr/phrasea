<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use Symfony\Component\Serializer\Annotation\Groups;

trait UpdatedAtDTOTrait
{
    #[Groups(['dates'])]
    protected \DateTimeImmutable $updatedAt;

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
