<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Cache;

use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class WorkflowCacheWarmer implements CacheWarmerInterface
{
    private WorkflowRepositoryInterface $fileWorkflowRepository;

    public function __construct(WorkflowRepositoryInterface $workflowRepository)
    {
        $this->fileWorkflowRepository = $workflowRepository;
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
