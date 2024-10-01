<?php

namespace App\Api\Model\Output;

use App\Entity\Core\Asset;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class PrepareDeleteAssetsOutput
{
    public function __construct(
        private bool $canDelete,
        private array $collections,
    ) {
    }

    #[Groups([Asset::GROUP_LIST])]
    public function getCollections(): array
    {
        return $this->collections;
    }

    #[Groups([Asset::GROUP_LIST])]
    public function isCanDelete(): bool
    {
        return $this->canDelete;
    }
}
