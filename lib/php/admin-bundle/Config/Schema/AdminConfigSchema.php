<?php

namespace Alchemy\AdminBundle\Config\Schema;

use Alchemy\ConfiguratorBundle\Schema\GlobalConfigurationSchema;
use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminConfigSchema implements SchemaProviderInterface
{
    public function __construct(
        #[Autowire(param: 'alchemy_core.app_name')]
        private string $serviceName,
    ) {
    }

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
                                new Assert\Regex(GlobalConfigurationSchema::LOGO_SRC_REGEX),
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
                name: 'title',
                description: 'Admin Title displayed in the web interface header.',
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Admin Configuration';
    }

    public function getRootKey(): string
    {
        return $this->serviceName.'.admin';
    }
}
