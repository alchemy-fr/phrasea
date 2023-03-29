<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Model\Workflow;

class FileWorkflowRepository implements WorkflowRepositoryInterface
{
    private string $dir;
    private YamlLoader $loader;

    public function __construct(string $dir, YamlLoader $loader)
    {

        $this->dir = $dir;
        $this->loader = $loader;
    }

    public function loadWorkflowByName(string $name): Workflow
    {
        return $this->loader->load($this->dir.'/'.$name.'.yaml');
    }
}
