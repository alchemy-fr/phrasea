<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;

class StoredFileFaker extends BaseProvider
{
    public function __construct(
        private readonly FileStorageManager $fileStorageManager,
        Generator $generator,
    ) {
        parent::__construct($generator);
    }

    /**
     * The size in bytes of a file already stored by another provider (e.g. imageUrlRandomRatio,
     * randomMedia), to be used as '<storedFileSize(@self->path)>' in a call run after the path is set.
     */
    public function storedFileSize(string $path): int
    {
        return $this->fileStorageManager->getSize($path);
    }
}
