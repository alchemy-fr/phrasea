<?php

namespace Alchemy\ConfiguratorBundle\Documentation;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Alchemy\CoreBundle\Documentation\DocumentationGenerator;

abstract class AbstractDocumentationGenerator extends DocumentationGenerator
{
    protected function renderSchema(SchemaProviderInterface $provider): ?string
    {
        return sprintf('## %s

%s', $provider->getTitle(), implode("\n", array_map([
            $this,
            'renderEntry',
        ], $provider->getSchema())));
    }

    protected function renderEntry(SchemaProperty $property, ?string $parentPath = null): string
    {
        $path = $parentPath ? $parentPath.'.'.$property->name : $property->name;
        $lines = [];
        $title = "### `{$path}`";
        if ($parentPath) {
            $title = '#'.$title;
        }
        $lines[] = $title;
        if ($property->description) {
            $lines[] = "\n".$property->description;
        }
        if (empty($property->children)) {
            $lines[] = "\n- Type: `string`";
            if ($property->example) {
                $lines[] = "\n- Example: `{$property->example}`";
            }
        } else {
            $lines[] = "\n- Type: `object` with the following properties:";

            foreach ($property->children as $child) {
                $lines[] = '';
                $lines[] = $this->renderEntry($child, $path);
            }
        }

        return implode("\n", $lines);
    }
}
