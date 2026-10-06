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

        return $this->getNamespaces($query);
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

        $matches = $this->filter(array_keys($namespaces), $query);

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
        $names = [];
        foreach ($this->metadataManipulator->getKnownTagGroups() as $id) {
            [$ns, $name] = explode(':', $id, 2) + [1 => ''];
            if (0 === strcasecmp($ns, $namespace)) {
                $names[] = $name;
                $namespace = $ns; // keep the case of the dictionary
            }
        }

        $tags = [];
        foreach ($this->filter($names, $query) as $name) {
            $id = $namespace.':'.$name;
            try {
                $tagGroup = $this->metadataManipulator->createTagGroup($id);
            } catch (TagUnknown) {
                continue;
            }

            $tags[] = new MetadataTag(
                id: $id,
                namespace: $namespace,
                name: $name,
                description: $tagGroup->getDescription('en'),
                writable: $tagGroup->isWritable(),
                multi: $tagGroup->isMulti(),
            );
        }

        return $tags;
    }

    /**
     * Case-insensitive match, prefix matches first.
     *
     * @param string[] $values
     *
     * @return string[]
     */
    private function filter(array $values, string $query): array
    {
        if ('' === $query) {
            return array_slice($values, 0, self::LIMIT);
        }

        $prefixed = [];
        $contained = [];
        foreach ($values as $value) {
            $pos = stripos($value, $query);
            if (0 === $pos) {
                $prefixed[] = $value;
            } elseif (false !== $pos) {
                $contained[] = $value;
            }
        }

        return array_slice([...$prefixed, ...$contained], 0, self::LIMIT);
    }
}
