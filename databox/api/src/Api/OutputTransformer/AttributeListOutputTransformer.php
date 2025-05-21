<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AttributeListItemOutput;
use App\Api\Model\Output\AttributeListOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\AttributeList\AttributeList;
use App\Entity\AttributeList\AttributeListItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class AttributeListOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeListItemOutputTransformer $attributeListItemOutputTransformer,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AttributeListOutput::class === $outputClass && $data instanceof AttributeList;
    }

    /**
     * @param AttributeList $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new AttributeListOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->title = $data->getTitle();
        $output->description = $data->getDescription();
        $output->public = $data->isPublic();

        if ($this->hasGroup([
            AttributeList::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());

            /** @var AttributeListItem[] $listItems */
            $listItems = $this->em->getRepository(AttributeList::class)
                ->getItemsIterator($data->getId());

            $output->items = [];
            foreach ($listItems as $item) {
                $output->items[] = $this->attributeListItemOutputTransformer->createOutput($item);
            }
        }

        if ($this->hasGroup([AttributeList::GROUP_LIST, AttributeList::GROUP_READ], $context)) {
            $output->setCapabilities([
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
