<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Api\Model\Output\WorkspaceSecretOutput;
use App\Entity\Integration\WorkspaceSecret;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: WorkspaceSecretOutput::class)]
class WorkspaceSecretOutputMapper implements OutputMapperInterface
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof WorkspaceSecret;
    }

    /**
     * @param WorkspaceSecret $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new WorkspaceSecretOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->name = $data->getName();
        if (null !== $workspace = $data->getWorkspace()) {
            $output->workspace = $this->iriConverter->getIriFromResource($workspace);
        }

        return $output;
    }
}
