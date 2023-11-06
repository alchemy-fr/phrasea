<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Cache;

use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class WorkflowCacheWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly WorkflowRepositoryInterface $fileWorkflowRepository)
    {
    }

    public function isOptional()
    {
        return true;
    }

    public function warmUp(string $cacheDir)
    {
        $this->fileWorkflowRepository->loadAll();
    }
}
