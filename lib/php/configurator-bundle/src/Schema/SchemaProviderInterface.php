<?php

namespace Alchemy\ConfiguratorBundle\Schema;

interface SchemaProviderInterface
{
    final public const string TAG = 'alchemy_configurator.schema_provider';

    /**
     * @return SchemaProperty[]
     */
    public function getSchema(): array;

    public function getTitle(): string;

    public function getRootKey(): string;
}
