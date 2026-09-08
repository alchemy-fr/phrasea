<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The metadata the application sets on a file, as opposed to FileMetadata which only ever
 * mirrors what was read from it.
 *
 * They live in their own table so that the read metadata stay untouched: these are the only
 * ones written back into rendition and export files.
 */
#[ORM\Entity]
class FileOverriddenMetadata extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * Normalized metadata: [ '<group>' => [ '<name>' => [values...] ] ].
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

    public function isEmpty(): bool
    {
        return [] === $this->metadata;
    }

    public function getMetadataNameValues(string $name): ?array
    {
        [$group, $tag] = self::splitName($name, false);
        if (null === $tag) {
            return null;
        }

        return $this->metadata[$group][$tag] ?? null;
    }

    /**
     * @return array<string, array> values indexed by "Group:Tag"
     */
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

    /**
     * @param string[] $seedValues values to start from when appending to a tag not overridden yet
     */
    public function setMetadataValue(string $name, mixed $value, bool $append = false, array $seedValues = []): void
    {
        [$group, $tag] = self::splitName($name, true);

        if ($append) {
            $this->metadata[$group][$tag] ??= $seedValues;
            $this->metadata[$group][$tag][] = $value;
        } else {
            $this->metadata[$group][$tag] = [$value];
        }
    }

    public function removeMetadataValue(string $name): void
    {
        [$group, $tag] = self::splitName($name, true);

        unset($this->metadata[$group][$tag]);
        if (empty($this->metadata[$group])) {
            unset($this->metadata[$group]);
        }
    }

    /**
     * @return array{?string, ?string}
     */
    private static function splitName(string $name, bool $strict): array
    {
        [$group, $tag] = array_pad(explode(':', $name, 2), 2, null);
        if (null === $tag && $strict) {
            throw new \InvalidArgumentException(sprintf('Metadata name "%s" must be a namespaced tag id (e.g. "IPTC:Keywords").', $name));
        }

        return [$group, $tag];
    }
}
