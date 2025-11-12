<?php

namespace Alchemy\ConfiguratorBundle\Service;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ConfigurationReference
{
    public function __construct(
        #[AutowireIterator(
            tag: SchemaProviderInterface::TAG,
        )]
        private iterable $schemaIterator,
    ) {
    }

    /**
     * @return SchemaProviderInterface[]
     */
    public function getSchemas(): iterable
    {
        return $this->schemaIterator;
    }

    /**
     * @return SchemaProperty[]
     */
    public function getAllSchemaProperties(): array
    {
        $allProperties = [];
        $keys = [];
        foreach ($this->getSchemas() as $schemaProvider) {
            $rootKey = $schemaProvider->getRootKey();
            $p = $rootKey;
            if (isset($keys[$rootKey])) {
                throw new \RuntimeException(sprintf('Duplicate root key "%s" found in multiple schema providers.', $rootKey));
            }

            $keys[$p] = true;

            foreach ($schemaProvider->getSchema() as $prop) {
                $this->visitProperty($allProperties, $prop, $rootKey);
            }
        }

        return $allProperties;
    }

    private function visitProperty(array &$allProps, SchemaProperty $prop, ?string $parentPath = null): void
    {
        $path = $parentPath ? $parentPath.'.'.$prop->name : $prop->name;

        if (empty($prop->children)) {
            $allProps[$path] = $prop;
        }

        foreach ($prop->children as $child) {
            $this->visitProperty($allProps, $child, $path);
        }
    }
}
