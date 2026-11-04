<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FileMetadata extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * Normalized metadata, as read from the file. The application never writes into it:
     * values it wants to embed into renditions or exports are computed at write time.
     */
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $metadata = [];

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getMetadataNameValues(string $name): ?array
    {
        [$group, $tag] = array_pad(explode(':', $name, 2), 2, null);
        if (null === $tag) {
            return null;
        }

        return $this->metadata[$group][$tag] ?? null;
    }

    public function getMetadataValues(): array
    {
        $result = [];
        foreach ($this->metadata as $group => $tags) {
            foreach ($tags as $tag => $values) {
                $result["$group:$tag"] = $values;
            }
        }

        return $result;
    }
}
