<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MergeAttributeEntitiesInput;
use App\Attribute\AttributeInterface;
use App\Consumer\Handler\Search\AttributeEntityMerge;
use App\Doctrine\Listener\AttributeEntityListener;
use App\Entity\Core\AttributeEntity;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class MergeAttributeEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostFlushStack $postFlushStack,
        private readonly AttributeEntityListener $attributeEntityListener,
    ) {
    }

    /**
     * @param MergeAttributeEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeEntity
    {
        $mainEntity = DoctrineUtil::findStrict($this->em, AttributeEntity::class, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $mainEntity);

        $locales = [
            AttributeInterface::NO_LOCALE => true,
        ];

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

        $merged = [];
        foreach ($entities as $entity) {
            foreach ($entity->getSynonyms() ?? [] as $locale => $s) {
                $locales[$locale] = true;
            }
            foreach ($entity->getTranslations() ?? [] as $locale => $s) {
                $locales[$locale] = true;
            }

            $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $entity);
            $synonyms = $this->mergeSynonyms($synonyms, $entity);
            $this->em->remove($entity);
            $merged[] = $entity->getId();
        }

        if (!empty($synonyms)) {
            $mainEntity->setSynonyms($synonyms);
        }

        $this->postFlushStack->addBusMessage(new AttributeEntityMerge(
            $mainEntity->getId(),
            $merged,
            array_keys($locales),
        ));

        $this->em->persist($mainEntity);

        try {
            $this->attributeEntityListener->disabled = true;
            $this->em->flush();
        } finally {
            $this->attributeEntityListener->disabled = false;
        }

        return $mainEntity;
    }

    private function mergeSynonyms(array $a, AttributeEntity $rightEntity): array
    {
        $b = $rightEntity->getSynonyms() ?? [];
        $a[AttributeInterface::NO_LOCALE] ??= [];
        $a[AttributeInterface::NO_LOCALE][] = $rightEntity->getValue();

        foreach ($rightEntity->getTranslations() ?? [] as $locale => $translation) {
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
