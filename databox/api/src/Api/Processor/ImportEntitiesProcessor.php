<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ImportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class ImportEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeEntityRepository $attributeEntityRepository,
    ) {
    }

    /**
     * @param ImportEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): EntityList
    {
        $listId = $uriVariables['id'];
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, $listId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $inputValues = $data->values ?? [];
        if ([] === $inputValues) {
            return $list;
        }

        $normalizedValues = array_values(array_unique($inputValues));

        foreach (array_chunk($normalizedValues, 2) as $chunk) { // TODO change number
            $qb = $this->attributeEntityRepository->createQueryBuilder('a')
                ->select('a.value')
                ->andWhere('a.list = :list')
                ->andWhere('a.value IN (:values)')
                ->setParameter('list', $list->getId())
                ->setParameter('values', $chunk);

            $existingRows = $qb->getQuery()->getScalarResult();

            $existingValues = array_column($existingRows, 'value');
            $normalizedValues = array_diff($normalizedValues, $existingValues);
        }

        if (empty($normalizedValues)) {
            return $list;
        }

        foreach ($normalizedValues as $value) {
            $entity = new AttributeEntity();
            $entity->setList($list);
            $entity->setValue($value);
            $this->em->persist($entity);
        }

        $this->em->flush();

        return $list;
    }
}
