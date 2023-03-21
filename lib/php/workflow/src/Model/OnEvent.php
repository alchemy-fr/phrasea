<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class OnEvent
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
