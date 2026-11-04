<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Model\MetadataTag;
use PHPExiftool\Exception\TagUnknown;

final readonly class MetadataTagProvider implements ProviderInterface
{
    use ParameterValuesTrait;

    private const int LIMIT = 50;
    private const int MIN_TAG_QUERY_LENGTH = 2;

    public function __construct(
        private MetadataManipulator $metadataManipulator,
    ) {
    }

    /**
     * @return MetadataTag[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $query = trim((string) self::getParameterValue($operation, 'query', ''));

        if (str_contains($query, ':')) {
            [$namespace, $name] = explode(':', $query, 2);

            return $this->getTags($namespace, $name);
        }

        $namespaces = $this->getNamespaces($query);
        if (mb_strlen($query) < self::MIN_TAG_QUERY_LENGTH) {
            return $namespaces;
        }

        return [...$namespaces, ...$this->getTagsOfAllNamespaces($query, self::LIMIT - count($namespaces))];
    }

    /**
     * @return MetadataTag[]
     */
    private function getNamespaces(string $query): array
    {
        $namespaces = [];
        foreach ($this->metadataManipulator->getKnownTagGroups() as $id) {
            $namespaces[explode(':', $id, 2)[0]] = true;
        }

        $matches = array_slice($this->filter(array_keys($namespaces), $query), 0, self::LIMIT);

        return array_map(fn (string $namespace): MetadataTag => new MetadataTag(
            id: $namespace,
            namespace: $namespace,
        ), $matches);
    }

    /**
     * @return MetadataTag[]
     */
    private function getTags(string $namespace, string $query): array
    {
        $ids = [];
        foreach ($this->metadataManipulator->getKnownTagGroups() as $id) {
            [$ns, $name] = explode(':', $id, 2) + [1 => ''];
            if (0 === strcasecmp($ns, $namespace)) {
                // keep the case of the dictionary
                $ids[$ns.':'.$name] = $name;
            }
        }

        return $this->createTags($this->filter($ids, $query), self::LIMIT);
    }

    /**
     * The tags of any namespace whose name (without the namespace) matches.
     *
     * @return MetadataTag[]
     */
    private function getTagsOfAllNamespaces(string $query, int $limit): array
    {
        $ids = [];
        foreach ($this->metadataManipulator->getKnownTagGroups() as $id) {
            if (str_contains($id, ':')) {
                $ids[$id] = explode(':', $id, 2)[1];
            }
        }

        return $this->createTags($this->filter($ids, $query), $limit);
    }

    /**
     * @param array<string, string> $names the tag names, indexed by tag ID
     *
     * @return MetadataTag[]
     */
    private function createTags(array $names, int $limit): array
    {
        $tags = [];
        foreach ($names as $id => $name) {
            if (count($tags) >= $limit) {
                break;
            }

            try {
                $tagGroup = $this->metadataManipulator->createTagGroup($id);
            } catch (TagUnknown) {
                continue;
            }

            $tags[] = new MetadataTag(
                id: $id,
                namespace: explode(':', $id, 2)[0],
                name: $name,
                description: $tagGroup->getDescription('en'),
                writable: $tagGroup->isWritable(),
                multi: $tagGroup->isMulti(),
            );
        }

        return $tags;
    }

    /**
     * Case-insensitive match, exact matches first, then prefix matches.
     *
     * @template K of array-key
     *
     * @param array<K, string> $values
     *
     * @return array<K, string>
     */
    private function filter(array $values, string $query): array
    {
        if ('' === $query) {
            return $values;
        }

        $exact = [];
        $prefixed = [];
        $contained = [];
        foreach ($values as $key => $value) {
            $pos = stripos($value, $query);
            if (0 === $pos) {
                if (strlen($value) === strlen($query)) {
                    $exact[$key] = $value;
                } else {
                    $prefixed[$key] = $value;
                }
            } elseif (false !== $pos) {
                $contained[$key] = $value;
            }
        }

        return $exact + $prefixed + $contained;
    }
}
