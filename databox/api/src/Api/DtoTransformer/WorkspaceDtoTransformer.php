<?php

declare(strict_types=1);

namespace App\Api\DtoTransformer;

use ApiPlatform\Metadata\Operation;
use App\Api\ApiSecurityTrait;
use App\Api\Model\Output\WorkspaceOutput;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;

class WorkspaceDtoTransformer implements OutputTransformerInterface
{
    use ApiSecurityTrait;

    private array $capCache = [];

    public function supports(string $outputClass, string $dataClass): bool
    {
        return WorkspaceOutput::class === $outputClass;
    }

    /**
     * @param Workspace $data
     */
    public function transform(object $data, string $outputClass, Operation $operation, array $context = []): object
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
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ];
        }

        $output->setCapabilities($this->capCache[$k]);

        return $output;
    }
}
