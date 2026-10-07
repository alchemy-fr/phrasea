<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\SavedSearchOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\SavedSearch\SavedSearch;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: SavedSearchOutput::class)]
class SavedSearchOutputMapper implements OutputMapperInterface
{
    use SecurityAwareTrait;
    use UserOutputTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof SavedSearch;
    }

    /**
     * @param SavedSearch $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new SavedSearchOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->name = $data->getName();
        $output->privacy = $data->getPrivacy()->value;
        $output->data = $data->getData();

        if ($this->hasGroup([
            SavedSearch::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());
        }

        if ($this->hasGroup([SavedSearch::GROUP_LIST, SavedSearch::GROUP_READ], $context)) {
            $output->setCapabilities([
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'editPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
