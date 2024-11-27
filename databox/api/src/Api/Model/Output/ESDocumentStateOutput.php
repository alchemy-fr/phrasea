<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

final readonly class ESDocumentStateOutput
{
    public function __construct(
        #[Groups(['_'])]
        private array $data,
        #[Groups(['_'])]
        private bool $synced,
    )
    {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getSynced(): bool
    {
        return $this->synced;
    }
}
