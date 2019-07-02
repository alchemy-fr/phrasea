<?php

declare(strict_types=1);

namespace App\User\Import\Loader;

interface UserImportLoaderInterface
{
    /**
     * @param resource $resource
     */
    public function import($resource, callable $createUser): iterable;
}
