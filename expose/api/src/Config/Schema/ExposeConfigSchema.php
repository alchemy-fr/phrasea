<?php

namespace App\Config\Schema;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ExposeConfigSchema implements SchemaProviderInterface
{
    public function getSchema(): array
    {
        return [
            new SchemaProperty(
                name: 'analytics',
                children: [
                    new SchemaProperty(
                        name: 'matomo',
                        children: [
                            new SchemaProperty(
                                name: 'baseUrl',
                                validationConstraints: [
                                    new Assert\NotBlank(),
                                    new Assert\Url(),
                                ],
                            ),
                            new SchemaProperty(
                                name: 'siteId',
                                validationConstraints: [
                                    new Assert\NotBlank(),
                                ],
                            ),
                        ]
                    ),
                ]
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Expose Application';
    }

    public function getRootKey(): string
    {
        return 'expose';
    }
}
