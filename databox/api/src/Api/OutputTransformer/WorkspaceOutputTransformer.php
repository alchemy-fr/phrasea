<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\WorkspaceOutput;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;

class WorkspaceOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use GroupsHelperTrait;

    private array $capCache = [];

    public function supports(string $outputClass, object $data): bool
    {
        return WorkspaceOutput::class === $outputClass && $data instanceof Workspace;
    }

    /**
     * @param Workspace $data
     */
    public function transform($data, string $outputClass, array &$context = []): object
    {
        $output = new WorkspaceOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());
        $output->setSlug($data->getSlug());
        $output->setEnabledLocales($data->getEnabledLocales());
        $output->setLocaleFallbacks($data->getLocaleFallbacks());
        $output->setPublic($data->isPublic());
        $output->setCreatedAt($data->getCreatedAt());

        $k = $data->getId().$this->getUserCacheId();
        if (!isset($this->capCache[$k])) {
            $this->capCache[$k] = [
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ];
        }

        if ($this->hasGroup([
            Collection::GROUP_READ,
            Collection::GROUP_LIST,
            Workspace::GROUP_LIST,
            Workspace::GROUP_LIST,
        ], $context)) {
            $output->setCapabilities($this->capCache[$k]);
        }

        return $output;
    }
}
