<?php

declare(strict_types=1);

namespace App\Integration\Config;

/**
 * For integrations whose configuration tree does not tell the whole story
 * (e.g. a polymorphic list): completes the schema dumped from the tree.
 */
interface ConfigSchemaCustomizerInterface
{
    public function customizeConfigSchema(array $schema): array;
}
