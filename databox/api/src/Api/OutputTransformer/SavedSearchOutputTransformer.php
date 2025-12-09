<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\SavedSearchOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\SavedSearch\SavedSearch;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class SavedSearchOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return SavedSearchOutput::class === $outputClass && $data instanceof SavedSearch;
    }

    /**
     * @param SavedSearch $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new SavedSearchOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->title = $data->getTitle();
        $output->public = $data->isPublic();
        $output->data = $data->getData();

        if ($this->hasGroup([
            SavedSearch::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());
        }

        if ($this->hasGroup([SavedSearch::GROUP_LIST, SavedSearch::GROUP_READ], $context)) {
            $output->setCapabilities([
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
