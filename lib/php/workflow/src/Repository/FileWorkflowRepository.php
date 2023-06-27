<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Loader\FileLoaderInterface;
use Alchemy\Workflow\Model\Workflow;
use Alchemy\Workflow\Model\WorkflowList;

class FileWorkflowRepository implements WorkflowRepositoryInterface
{
    private ?WorkflowList $workflows = null;

    public function __construct(private readonly array $dirs, private readonly FileLoaderInterface $loader)
    {
    }

    private function doLoad(): void
    {
        if (null === $this->workflows) {
            $this->workflows = new WorkflowList();
            foreach ($this->dirs as $dir) {
                foreach (scandir($dir) as $file) {
                    $path = $dir.'/'.$file;
                    if (!is_dir($path)) {
                        $this->workflows->append($this->loader->load($path));
                    }
                }
            }
        }
    }

    public function loadWorkflowByName(string $name): ?Workflow
    {
        $this->doLoad();

        return $this->workflows->getByName($name);
    }

    public function getWorkflowsByEvent(WorkflowEvent $event): array
    {
        $this->doLoad();

        return $this->workflows->getByEventName($event->getName());
    }

    public function loadAll(): void
    {
        $this->doLoad();
    }
}
