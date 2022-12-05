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
    private string $label;

    /**
     * @Groups({"_"})
     */
    private $value;

    public function __construct(string $type, string $label, $value)
    {

        $this->label = $label;
        $this->value = $value;
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue()
    {
        return $this->value;
    }
}
