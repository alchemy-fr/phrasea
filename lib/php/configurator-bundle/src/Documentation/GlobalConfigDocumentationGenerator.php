<?php

namespace Alchemy\ConfiguratorBundle\Documentation;

use Alchemy\ConfiguratorBundle\Schema\GlobalConfigurationSchema;

final class GlobalConfigDocumentationGenerator extends AbstractDocumentationGenerator
{
    public function __construct(
        private GlobalConfigurationSchema $schema,
    ) {
    }

    public function getPath(): string
    {
        return '_global_config.md';
    }

    public function getContent(): ?string
    {
        return $this->renderSchema($this->schema);
    }
}
