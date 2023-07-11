<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\WorkspaceOutput;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;

class WorkspaceOutputProcessor extends AbstractSecurityProcessor
{
    private array $capCache = [];

    /**
     * @param Workspace $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new WorkspaceOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setSlug($data->getSlug());
        $output->setEnabledLocales($data->getEnabledLocales());
        $output->setLocaleFallbacks($data->getLocaleFallbacks());
        $output->setPublic($data->isPublic());
        $output->setCreatedAt($data->getCreatedAt());

        $k = $data->getId().$this->getTokenId();
        if (!isset($this->capCache[$k])) {
            $this->capCache[$k] = [
                'canEdit' => $this->isGranted(WorkspaceVoter::EDIT, $data),
                'canDelete' => $this->isGranted(WorkspaceVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(WorkspaceVoter::EDIT_PERMISSIONS, $data),
            ];
        }

        $output->setCapabilities($this->capCache[$k]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceOutput::class === $to && $data instanceof Workspace;
    }
}
