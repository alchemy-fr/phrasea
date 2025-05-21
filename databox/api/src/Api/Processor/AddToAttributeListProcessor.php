<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddToAttributeListInput;
use App\Api\Model\Input\AttributeListItemInput;
use App\Entity\AttributeList\AttributeListItem;
use App\Entity\AttributeList\AttributeList;
use App\Entity\Core\AttributeDefinition;
use App\Repository\AttributeList\AttributeListRepository;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AddToAttributeListProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributeListRepository $attributeListRepository,
        private readonly IriConverterInterface $iriConverter,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AddToAttributeListInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeList
    {
        $user = $this->getStrictUser();
        if (isset($uriVariables['id'])) {
            $attributeListId = $uriVariables['id'];
            $attributeList = DoctrineUtil::findStrictByRepo($this->attributeListRepository, $attributeListId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $attributeList);
        } else {
            $attributeList = $this->attributeListRepository->findOneBy([
                'ownerId' => $user->getId(),
            ], [
                'createdAt' => 'ASC',
            ]);
        }

        if (null === $attributeList) {
            $attributeList = new AttributeList();
            $attributeList->setOwnerId($user->getId());
            $this->em->persist($attributeList);
            $position = 0;
        } else {
            $position = $this->attributeListRepository->getMaxPosition($attributeList->getId()) + 1;
        }

        foreach ($data->items as $i) {
            $item = new AttributeListItem();
            $item->setList($attributeList);
            $item->setType($i->type);
            $item->setPosition($position++);

            switch ($i->type) {
                case AttributeListItem::TYPE_ATTR_DEF:
                    $definition = DoctrineUtil::findStrictByRepo($this->attributeDefinitionRepository, $i->definition);
                    $this->denyAccessUnlessGranted(AbstractVoter::READ, $definition);
                    $item->setDefinition($definition);
                    if ($this->attributeListRepository->hasDefinition($attributeList->getId(), $definition->getId())) {
                        continue 2;
                    }
                    break;
                case AttributeListItem::TYPE_DIVIDER:
                case AttributeListItem::TYPE_BUILT_IN:
                    $item->setKey($i->key);
                    break;
                case AttributeListItem::TYPE_SPACER:
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported type "%d"', $i->type));
            }
            $this->em->persist($item);
        }

        $this->em->flush();

        return $attributeList;
    }
}
