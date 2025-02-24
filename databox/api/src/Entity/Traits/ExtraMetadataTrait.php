<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait ExtraMetadataTrait
{
    #[Groups(['_'])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extraMetadata = null;

    public function getExtraMetadata(): array
    {
        return $this->extraMetadata ?? [];
    }

    public function setExtraMetadata(?array $extraMetadata): void
    {
        $this->extraMetadata = $extraMetadata;
    }

    public function addExtraMetadata(string $key, mixed $value): void
    {
        $this->extraMetadata[$key] = $value;
    }
}
