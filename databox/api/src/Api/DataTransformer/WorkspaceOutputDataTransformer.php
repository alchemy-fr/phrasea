<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\WorkspaceOutput;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;

class WorkspaceOutputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param Workspace $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new WorkspaceOutput();
        $output->setId($object->getId());
        $output->setName($object->getName());
        $output->setSlug($object->getSlug());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(WorkspaceVoter::EDIT, $object),
            'canDelete' => $this->isGranted(WorkspaceVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(WorkspaceVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceOutput::class === $to && $data instanceof Workspace;
    }
}
