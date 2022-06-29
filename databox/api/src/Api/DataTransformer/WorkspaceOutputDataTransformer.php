<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\WorkspaceOutput;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;

class WorkspaceOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private array $capCache = [];

    /**
     * @param Workspace $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new WorkspaceOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());
        $output->setSlug($object->getSlug());
        $output->setEnabledLocales($object->getEnabledLocales());
        $output->setLocaleFallbacks($object->getLocaleFallbacks());
        $output->setCreatedAt($object->getCreatedAt());

        $k = $object->getId();
        if (!isset($this->capCache[$k])) {
            $this->capCache[$k] = [
                'canEdit' => $this->isGranted(WorkspaceVoter::EDIT, $object),
                'canDelete' => $this->isGranted(WorkspaceVoter::DELETE, $object),
                'canEditPermissions' => $this->isGranted(WorkspaceVoter::EDIT_PERMISSIONS, $object),
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
