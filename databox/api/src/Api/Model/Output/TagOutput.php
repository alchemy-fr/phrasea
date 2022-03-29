<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class TagOutput extends AbstractUuidOutput
{
    /**
     * @Groups({"asset:index", "asset:read", "tag:index", "tag:read"})
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
