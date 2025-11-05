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

%s', $provider->getTitle(), implode("\n", array_map(function (SchemaProperty $property) use ($provider): string {
            return $this->renderEntry($property, $provider->getRootKey());
        }, $provider->getSchema())));
    }

    protected function renderEntry(SchemaProperty $property, ?string $parentPath = null, int $level = 0): string
    {
        $path = $parentPath ? $parentPath.'.'.$property->name : $property->name;
        $lines = [];
        $title = "### `{$path}`";
        if ($level > 0) {
            $title = '#'.$title;
        }
        $lines[] = $title;
        if ($property->description) {
            $lines[] = "\n".$property->description;
        }
        if (empty($property->children)) {
            $lines[] = "\n- Type: `string`";
            if ($property->example) {
                if (str_contains($property->example, "\n")) {
                    $lines[] = "\n- Example:\n\n```\n{$property->example}\n```";
                } else {
                    $lines[] = "\n- Example: `{$property->example}`";
                }
            }
        } else {
            $lines[] = "\n- Type: `object` with the following properties:";

            foreach ($property->children as $child) {
                $lines[] = '';
                $lines[] = $this->renderEntry($child, $path, $level + 1);
            }
        }

        return implode("\n", $lines);
    }
}
