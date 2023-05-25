<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class AbstractUuidOutput
{
    /**
     * The unique resource ID (UUID form).
     *
     * @ApiProperty(identifier=true)
     *
     * @Groups({"_"})
     */
    protected string $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
