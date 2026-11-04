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
                description: 'Organisation theme of the Databox client (JSON): a light palette of hex colors, an optional dark alternative, a few style properties and, optionally, the font files themselves (data URIs, served to the browser as @font-face rules). Offered to every user in the theme menu next to the light / dark appearance. Managed from the client ("Customize theme…" in the settings menu of an administrator).',
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
  "fontFamily": "Acme Sans",
  "letterSpacing": 0.01,
  "fonts": [
    {
      "family": "Acme Sans",
      "src": "data:font/woff2;base64,d09GMgABAAAA...",
      "weight": "normal",
      "style": "normal"
    }
  ]
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
