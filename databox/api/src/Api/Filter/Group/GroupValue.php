<?php

declare(strict_types=1);

namespace App\Api\Filter\Group;

use Symfony\Component\Serializer\Annotation\Groups;

readonly class GroupValue
{
    public function __construct(
        #[Groups(['_'])]
        private string $name,
        #[Groups(['_'])]
        private string $type,
        #[Groups(['_'])]
        private ?string $key,
        #[Groups(['_'])]
        private array $values
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
