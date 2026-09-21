<?php

declare(strict_types=1);

namespace App\Config\Schema;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use App\Validator\ValidClientThemeConstraint;

final class DataboxConfigSchema implements SchemaProviderInterface
{
    /** Full key of the organisation theme entry (`<root key>.<property>`) */
    final public const string THEME_KEY = 'databox.theme';

    public function getSchema(): array
    {
        return [
            new SchemaProperty(
                name: 'theme',
                description: 'Organisation theme of the Databox client (JSON): a light palette of hex colors, an optional dark alternative and a few style properties, offered to every user in the theme menu next to the light / dark appearance. Managed from the client ("Customize theme…" in the settings menu of an administrator).',
                example: <<<'EOT'
{
  "name": "Acme",
  "default": true,
  "colors": {
    "primary": "#ff6600",
    "primary-foreground": "#ffffff"
  },
  "dark": {
    "primary": "#ff8a3d"
  },
  "radius": 0.75,
  "fontSize": 14,
  "fontFamily": "Inter, sans-serif",
  "letterSpacing": 0.01
}
EOT,
                validationConstraints: [
                    new ValidClientThemeConstraint(),
                ],
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Databox Application';
    }

    public function getRootKey(): string
    {
        return 'databox';
    }
}
