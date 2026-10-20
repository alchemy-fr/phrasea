<?php

declare(strict_types=1);

namespace App\Config\Schema;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ParadeConfigSchema implements SchemaProviderInterface
{
    final public const string ENABLED_KEY = 'parade.enabled';
    final public const string URL_KEY = 'parade.url';

    public function getSchema(): array
    {
        return [
            new SchemaProperty(
                name: 'enabled',
                description: 'Whether Parade is enabled for this stack ("true" or "false"). Managed from the Parade menu of the Databox admin.',
                example: 'true',
                validationConstraints: [
                    new Assert\Choice(choices: ['true', 'false']),
                ],
            ),
            new SchemaProperty(
                name: 'url',
                description: 'Public URL of the Parade client. Managed from the Parade menu of the Databox admin.',
                example: 'https://parade.example.com',
                validationConstraints: [
                    new Assert\Url(requireTld: false),
                ],
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Parade';
    }

    public function getRootKey(): string
    {
        return 'parade';
    }
}
