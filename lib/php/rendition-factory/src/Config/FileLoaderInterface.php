<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;

interface FileLoaderInterface
{
    public function load(string $file): BuildConfig;
}
