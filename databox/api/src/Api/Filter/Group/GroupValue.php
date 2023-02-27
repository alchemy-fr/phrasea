<?php

declare(strict_types=1);

namespace App\Api\Filter\Group;

use Symfony\Component\Serializer\Annotation\Groups;

class GroupValue
{
    /**
     * @Groups({"_"})
     */
    private string $type;

    /**
     * @Groups({"_"})
     */
    private string $key;

    /**
     * @Groups({"_"})
     */
    private array $values;

    public function __construct(string $type, string $key, array $values)
    {
        $this->type = $type;
        $this->key = $key;
        $this->values = $values;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
