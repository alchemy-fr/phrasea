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

    private ?string $cachedChecksum = null;

    /**
     * Normalized metadata.
     */
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $metadata = [];

    /**
     * Original metadata checksum.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
    private ?string $checksum = null;

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->cachedChecksum = null;
        if (null === $this->checksum) {
            $this->checksum = $this->getComputedChecksum();
        }
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

    public function setMetadataValue(string $name, mixed $value, bool $append = false): void
    {
        [$group, $tag] = array_pad(explode(':', $name, 2), 2, null);
        if (null === $tag) {
            throw new \InvalidArgumentException(sprintf('Metadata name "%s" must be a namespaced tag id (e.g. "IPTC:Keywords").', $name));
        }

        $this->metadata[$group][$tag] ??= [];

        if ($append) {
            $this->metadata[$group][$tag][] = $value;
        } else {
            $this->metadata[$group][$tag] = [$value];
        }
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function metadataHasChanged(): bool
    {
        return $this->checksum !== $this->getComputedChecksum();
    }

    private function getComputedChecksum(): string
    {
        if (null === $this->cachedChecksum) {
            $this->cachedChecksum = hash('sha256', serialize($this->metadata));
        }

        return $this->cachedChecksum;
    }
}
