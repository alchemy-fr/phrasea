<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Loader;

use Alchemy\Workflow\Model\Workflow;

interface FileLoaderInterface
{
    public function load(string $file): Workflow;
}
