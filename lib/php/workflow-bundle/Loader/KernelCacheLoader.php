<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Loader;

use Alchemy\Workflow\Loader\FileLoaderInterface;
use Alchemy\Workflow\Model\Workflow;
use Symfony\Component\Config\ConfigCache;

class KernelCacheLoader implements FileLoaderInterface
{
    public function __construct(private readonly FileLoaderInterface $fileLoader, private readonly string $cacheDir, private readonly bool $debug)
    {
    }

    public function load(string $file): Workflow
    {
        $cache = new ConfigCache($this->cacheDir.'/'.basename($file).'-'.md5($file), $this->debug);

        if ($cache->isFresh()) {
            return unserialize(file_get_contents($cache->getPath()));
        }

        $workflow = $this->fileLoader->load($file);

        $cache->write(serialize($workflow));

        return $workflow;
    }
}
