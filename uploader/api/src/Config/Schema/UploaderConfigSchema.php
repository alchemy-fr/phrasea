<?php

namespace App\Config\Schema;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class UploaderConfigSchema implements SchemaProviderInterface
{
    public function getSchema(): array
    {
        return [
            new SchemaProperty(
                name: 'max_upload_file_size',
                description: 'Maximum allowed file size for uploads in bytes.',
                validationConstraints: [
                    new Assert\Type('numeric'),
                    new Assert\Positive(),
                ],
            ),
            new SchemaProperty(
                name: 'max_upload_commit_size',
                description: 'Maximum allowed total size for a commit of uploads in bytes.',
                validationConstraints: [
                    new Assert\Type('numeric'),
                    new Assert\Positive(),
                ],
            ),
            new SchemaProperty(
                name: 'max_upload_file_count',
                description: 'Maximum allowed number of files in a single upload.',
                validationConstraints: [
                    new Assert\Type('numeric'),
                    new Assert\Positive(),
                ],
            ),
        ];
    }

    public function getTitle(): string
    {
        return 'Uploader Application';
    }

    public function getRootKey(): string
    {
        return 'uploader';
    }
}
