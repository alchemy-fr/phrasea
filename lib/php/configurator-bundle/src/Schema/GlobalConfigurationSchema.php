<?php

namespace Alchemy\ConfiguratorBundle\Schema;

use Symfony\Component\Validator\Constraints as Assert;

final class GlobalConfigurationSchema implements SchemaProviderInterface
{
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
                                new Assert\Regex('#^data:image\\/(png|jpg|jpeg|gif);base64,[a-zA-Z0-9+/=]+$#'),
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
