<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\ProfileOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: ProfileOutput::class)]
class ProfileOutputMapper implements OutputMapperInterface
{
    use SecurityAwareTrait;
    use UserOutputTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProfileItemOutputMapper $profileItemOutputMapper,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof Profile;
    }

    /**
     * @param Profile $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new ProfileOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->name = $data->getName();
        $output->description = $data->getDescription();
        $output->public = $data->isPublic();

        $output->owner = $this->transformUser($data->getOwnerId());

        if ($this->hasGroup([
            Profile::GROUP_READ,
        ], $context)) {
            $output->data = $data->getData()?->getData() ?? [];

            /** @var ProfileItem[] $profileItems */
            $profileItems = $this->em->getRepository(Profile::class)
                ->getItemsIterator($data->getId());

            $output->items = [];
            foreach ($profileItems as $item) {
                if (null !== $attributeDefinition = $item->getDefinition()) {
                    if (!$this->security->isGranted(AbstractVoter::READ, $attributeDefinition)) {
                        continue;
                    }
                }
                $output->items[] = $this->profileItemOutputMapper->createOutput($item);
            }
        }

        if ($this->hasGroup([Profile::GROUP_LIST, Profile::GROUP_READ], $context)) {
            $output->setCapabilities([
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'editPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
