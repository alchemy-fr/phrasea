<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Api\Model\Output\WorkspaceEnvOutput;
use App\Entity\Integration\WorkspaceEnv;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: WorkspaceEnvOutput::class)]
class WorkspaceEnvOutputMapper implements OutputMapperInterface
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

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
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->name = $data->getName();
        $output->value = $data->getValue();
        if (null !== $workspace = $data->getWorkspace()) {
            $output->workspace = $this->iriConverter->getIriFromResource($workspace);
        }

        return $output;
    }
}
