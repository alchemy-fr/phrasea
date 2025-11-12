<?php

namespace Alchemy\ConfiguratorBundle\Documentation;

use Alchemy\ConfiguratorBundle\Schema\GlobalConfigurationSchema;
use Alchemy\ConfiguratorBundle\Service\ConfigurationReference;

final class AppConfigDocumentationGenerator extends AbstractDocumentationGenerator
{
    public function __construct(
        private ConfigurationReference $configurationReference,
    ) {
    }

    public function getPath(): string
    {
        return '_app_config.md';
    }

    public function getContent(): ?string
    {
        $content = [];
        foreach ($this->configurationReference->getSchemas() as $schema) {
            if ($schema instanceof GlobalConfigurationSchema) {
                continue;
            }

            $rendered = trim($this->renderSchema($schema));
            if ($rendered) {
                $content[] = $rendered;
            }
        }

        return trim(implode("\n", $content));
    }
}
