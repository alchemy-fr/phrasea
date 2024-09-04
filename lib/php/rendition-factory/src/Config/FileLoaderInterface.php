<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\FamilyBuildConfig;

interface FileLoaderInterface
{
    public function load(string $file): BuildConfig;
}
