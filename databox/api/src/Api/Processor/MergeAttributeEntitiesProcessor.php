<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MergeAttributeEntitiesInput;
use App\Attribute\AttributeInterface;
use App\Entity\Core\AttributeEntity;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class MergeAttributeEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param MergeAttributeEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeEntity
    {
        $mainEntity = DoctrineUtil::findStrict($this->em, AttributeEntity::class, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $mainEntity);

        /** @var AttributeEntity[] $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('t')
            ->from(AttributeEntity::class, 't')
            ->andWhere('t.id IN (:ids)')
            ->andWhere('t.list  = :list')
            ->setParameter('list', $mainEntity->getList()->getId())
            ->setParameter('ids', $data->ids)
            ->getQuery()
            ->getResult();

        $synonyms = $mainEntity->getSynonyms() ?? [];

        foreach ($entities as $entity) {
            $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $entity);
            $synonyms = $this->mergeSynonyms($synonyms, $entity);
            $this->em->remove($entity);
        }

        if (!empty($synonyms)) {
            $mainEntity->setSynonyms($synonyms);
        }

        $this->em->persist($mainEntity);
        $this->em->flush();

        return $mainEntity;
    }

    private function mergeSynonyms(array $a, AttributeEntity $rightEntity): array
    {
        $b = $rightEntity->getSynonyms() ?? [];
        $a[AttributeInterface::NO_LOCALE] ??= [];
        $a[AttributeInterface::NO_LOCALE][] = $rightEntity->getValue();

        foreach ($rightEntity->getTranslations() as $locale => $translation) {
            $a[$locale] ??= [];
            $a[$locale][] = $translation;
        }

        foreach ($b as $locale => $synonyms) {
            $a[$locale] = array_merge($a[$locale] ?? [], $synonyms);
        }

        foreach ($a as $locale => $synonyms) {
            $a[$locale] = array_values(array_unique(array_filter($synonyms, fn (?string $s): bool => !empty($s))));

            if (empty($a[$locale])) {
                unset($a[$locale]);
            }
        }

        return $a;
    }
}
