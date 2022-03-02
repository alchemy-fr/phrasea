<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;

trait CreatedAtDTOTrait
{
    /**
     * @ApiProperty()
     * @Groups({"_"})
     */
    protected DateTimeInterface $createdAt;

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
