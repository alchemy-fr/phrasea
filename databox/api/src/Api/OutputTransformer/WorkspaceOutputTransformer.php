<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\WorkspaceOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;

class WorkspaceOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use GroupsHelperTrait;
    use UserLocaleTrait;
    use UserOutputTransformerTrait;

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
        $output->nameTranslated = $data->getTranslatedField('name', $this->getPreferredLocales($data), $data->getName());
        $output->setSlug($data->getSlug());
        $output->setEnabledLocales($data->getEnabledLocales());
        $output->setLocaleFallbacks($data->getLocaleFallbacks());
        $output->setPublic($data->isPublic());
        $output->setCreatedAt($data->getCreatedAt());
        $output->translations = $data->getTranslations();
        $output->ownerId = $data->getOwnerId();

        $k = $data->getId().$this->getUserCacheId();
        if (!isset($this->capCache[$k])) {
            $this->capCache[$k] = [
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ];
        }

        if ($this->hasGroup([
            Workspace::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());
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
