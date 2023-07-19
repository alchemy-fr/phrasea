<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Api\Model\Output\WorkspaceOutput;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\SecurityAwareTrait;
use App\Security\Voter\AbstractVoter;

final class WorkspaceProvider extends AbstractTransformerProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    private array $capCache = [];

    protected function transform(object $data, array $context): object
    {
        throw new \InvalidArgumentException(sprintf('OK'));
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

        $output->setCapabilities($this->capCache[$k]);

        return $output;
    }


}
