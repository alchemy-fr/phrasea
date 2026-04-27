<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\ProfileOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class ProfileOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProfileItemOutputTransformer $profileItemOutputTransformer,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return ProfileOutput::class === $outputClass && $data instanceof Profile;
    }

    /**
     * @param Profile $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new ProfileOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->title = $data->getTitle();
        $output->description = $data->getDescription();
        $output->public = $data->isPublic();

        if ($this->hasGroup([
            Profile::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());

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
                $output->items[] = $this->profileItemOutputTransformer->createOutput($item);
            }
        }

        if ($this->hasGroup([Profile::GROUP_LIST, Profile::GROUP_READ], $context)) {
            $output->setCapabilities([
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
