<?php

namespace Alchemy\ConfiguratorBundle\Schema;

use Symfony\Component\Validator\Constraints as Assert;

final class GlobalConfigurationSchema implements SchemaProviderInterface
{
    final public const string LOGO_SRC_REGEX = '#^data:image\\/(png|jpg|jpeg|gif|svg\+xml);base64,[a-zA-Z0-9+/=]+$#';

    public function getSchema(): array
    {
        return [
            new SchemaProperty(
                name: 'logo',
                description: 'Logo displayed in the web interface header.',
                children: [
                    new SchemaProperty(
                        name: 'src',
                        description: 'Can be a URL or a base64-encoded image.',
                        validationConstraints: [
                            new Assert\AtLeastOneOf([
                                new Assert\Url(),
                                new Assert\Regex(self::LOGO_SRC_REGEX),
                            ]),
                        ]
                    ),
                    new SchemaProperty(
                        name: 'style',
                        description: 'CSS styles applied to the logo image (e.g., "width: 100px; height: auto;").',
                        example: 'width: 100px; height: auto;',
                    ),
                ],
            ),
            new SchemaProperty(
                name: 'theme',
                description: 'Theme copied from https://bareynol.github.io/mui-theme-creator/',
                example: <<<'EOT'
export const themeOptions = {
  palette: {
    type: 'light',
    primary: {
      main: '#3f51b5',
    },
    secondary: {
      main: '#f50057',
    },
    background: {
      default: '#793333',
    },
  },
};
EOT
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Global Configuration';
    }

    public function getRootKey(): string
    {
        return '';
    }
}
