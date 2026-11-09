<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\WorkspaceEnvOutput;
use App\Entity\Integration\WorkspaceEnv;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: WorkspaceEnvOutput::class)]
class WorkspaceEnvOutputMapper implements OutputMapperInterface
{
    public function supports(object $data): bool
    {
        return $data instanceof WorkspaceEnv;
    }

    /**
     * @param WorkspaceEnv $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new WorkspaceEnvOutput();
        $output->setId($data->getId());
        $output->name = $data->getName();

        return $output;
    }
}
