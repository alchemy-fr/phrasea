<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddToAttributeListInput;
use App\Api\Model\Input\DefinitionToAttributeListInput;
use App\Entity\AttributeList\AttributeListDefinition;
use App\Entity\AttributeList\AttributeList;
use App\Entity\Core\AttributeDefinition;
use App\Repository\AttributeList\AttributeListRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AddToAttributeListProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
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

        $ids = array_map(function (DefinitionToAttributeListInput|string $input): string {
            if (is_string($input)) {
                return $input;
            }

            return $input->id;
        }, $data->definitions);

        $definitions = $this->em->getRepository(AttributeDefinition::class)
            ->findByIds($ids);

        foreach ($definitions as $definition) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $definition);
            $item = new AttributeListDefinition();
            $item->setList($attributeList);
            $item->setDefinition($definition);
            $item->setPosition($position++);
            $this->em->persist($item);
        }

        $this->em->flush();

        return $attributeList;
    }
}
