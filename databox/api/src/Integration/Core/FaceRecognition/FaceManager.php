<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Integration\IntegrationConfig;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Links the detected faces (attribute values carrying an embedding) to the persons (entities of
 * the configured list): recognition, propagation of a user decision, pending persons for the
 * look-alikes nobody has named yet.
 *
 * The references of a person are the faces users named (ORIGIN_HUMAN): a recognized face never
 * becomes a reference, so that the recognition cannot drift.
 */
final class FaceManager
{
    /** @var array<string, bool> */
    private array $pendingPersons = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeRepository $attributeRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributeAssigner $attributeAssigner,
        private readonly FaceRecognitionConfigResolver $configResolver,
        private readonly DeferredIndexListener $deferredIndexListener,
    ) {
    }

    /**
     * The person of a face among the known faces: the closest face a user named, or else the
     * closest face of a person nobody named yet (a pending entity). A face matching only the
     * recognized faces of a named person is left unknown.
     *
     * @param float[]                                                      $vector
     * @param iterable<array{value: string, origin: int, vector: float[]}> $faces
     *
     * @return array{value: string, confidence: float}|null the entity ID and the similarity
     */
    public function findPerson(array $vector, iterable $faces, float $threshold): ?array
    {
        if ($threshold <= 0) {
            return null;
        }
        // The persons may have been approved or removed since the last call
        $this->pendingPersons = [];

        $bestHuman = null;
        $bestAny = null;
        foreach ($faces as $face) {
            $similarity = FaceMatcher::cosineSimilarity($vector, $face['vector']);
            if ($similarity < $threshold) {
                continue;
            }
            $match = ['value' => (string) $face['value'], 'confidence' => $similarity];
            if (Attribute::ORIGIN_HUMAN === (int) $face['origin'] && $similarity > ($bestHuman['confidence'] ?? -INF)) {
                $bestHuman = $match;
            }
            if ($similarity > ($bestAny['confidence'] ?? -INF)) {
                $bestAny = $match;
            }
        }

        if (null !== $bestHuman) {
            return $bestHuman;
        }
        if (null !== $bestAny && $this->isPendingPerson($bestAny['value'])) {
            return $bestAny;
        }

        return null;
    }

    /**
     * A new person nobody has named yet, in the list of the configuration.
     */
    public function createUnknownPerson(IntegrationConfig $config): AttributeEntity
    {
        $list = $this->configResolver->getEntityList($config);

        $entity = new AttributeEntity();
        $entity->setList($list);
        $entity->setWorkspace($list->getWorkspace());
        $entity->setValue(sprintf('%s · %s', $config['unknownLabel'] ?: FaceRecognitionIntegration::DEFAULT_UNKNOWN_LABEL, substr(bin2hex(random_bytes(4)), 0, 4)));
        $entity->setStatus(AttributeEntity::STATUS_PENDING);
        $this->em->persist($entity);

        return $entity;
    }

    /**
     * Removes the pending persons of the list that no face references any more.
     *
     * @param string[]|null $values the entity IDs to check (every pending entity of the list without it)
     *
     * @return int the number of persons removed
     */
    public function removeOrphanUnknowns(EntityList $list, ?array $values = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(AttributeEntity::class, 'e')
            ->andWhere('e.list = :list')
            ->andWhere('e.status = :pending')
            ->andWhere('e.creatorId IS NULL')
            ->setParameter('list', $list->getId())
            ->setParameter('pending', AttributeEntity::STATUS_PENDING);
        if (null !== $values) {
            $values = array_values(array_unique(array_filter($values)));
            if (empty($values)) {
                return 0;
            }
            $qb
                ->andWhere('e.id IN (:ids)')
                ->setParameter('ids', $values);
        }
        /** @var AttributeEntity[] $pending */
        $pending = $qb->getQuery()->getResult();
        if (empty($pending)) {
            return 0;
        }

        $definitionIds = array_map(fn ($d) => $d->getId(), $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity($list->getWorkspaceId(), $list->getId()));
        $unused = array_flip($this->attributeRepository->findUnusedValues($definitionIds, array_map(fn (AttributeEntity $e): string => $e->getId(), $pending)));

        $count = 0;
        foreach ($pending as $entity) {
            if (isset($unused[$entity->getId()])) {
                $this->em->remove($entity);
                ++$count;
            }
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /**
     * Makes the most confident face of the person a reference (named by the user), when the
     * person has none yet: what happens when a user approves a pending person.
     */
    public function markReference(AttributeEntity $person, IntegrationConfig $config, ?string $userId): ?Attribute
    {
        $definition = $this->configResolver->getDefinition($config);
        $reference = $this->attributeRepository->findMostConfidentEmbedding($definition->getId(), $person->getId());
        if (!$reference instanceof Attribute) {
            return null;
        }
        if (Attribute::ORIGIN_HUMAN === $reference->getOrigin()) {
            return $reference;
        }

        $this->confirm($reference, $userId);
        FaceRecognitionDoctrineListener::suspend(fn () => $this->em->flush());

        return $reference;
    }

    /**
     * Marks a face as named by a user: it becomes a reference of its person.
     */
    public function confirm(Attribute $face, ?string $userId): void
    {
        $face->setOrigin(Attribute::ORIGIN_HUMAN);
        $face->setOriginUserId($userId);
        $face->setConfidence(1.0);
        $this->attributeAssigner->resetAssetAttributesCache($face->getAsset());
    }

    /**
     * Applies the person of a face named by a user to the similar faces of the workspace that no
     * user named: the faces of pending persons, the unknown ones, and the recognized ones when the
     * new reference is closer than the one they were recognized from.
     *
     * @return int the number of faces that changed person
     */
    public function propagate(Attribute $reference, IntegrationConfig $config): int
    {
        $threshold = (float) $config['matchThreshold'];
        $vector = $reference->getEmbedding()['v'] ?? null;
        if ($threshold <= 0 || null === $vector || Attribute::ORIGIN_HUMAN !== $reference->getOrigin()) {
            return 0;
        }

        $definition = $this->configResolver->getDefinition($config);
        $person = (string) $reference->getValue();
        $list = $this->configResolver->getEntityList($config);

        $count = 0;
        $previous = [];
        $this->pendingPersons = [];
        foreach ($this->attributeRepository->iterateMachineEmbeddings($definition->getId(), $reference->getAsset()->getId()) as $candidate) {
            $similarity = FaceMatcher::cosineSimilarity($vector, $candidate->getEmbedding()['v'] ?? []);
            $current = (string) $candidate->getValue();
            $closer = $current === $person
                || $this->isPendingPerson($current)
                || $similarity > $candidate->getConfidence();

            if ($similarity >= $threshold && $closer) {
                if ($current !== $person) {
                    $previous[$current] = true;
                    $candidate->setValue($person);
                    ++$count;
                }
                $candidate->setConfidence($similarity);
                $this->attributeAssigner->resetAssetAttributesCache($candidate->getAsset());
            } else {
                $this->em->detach($candidate);
            }
        }
        FaceRecognitionDoctrineListener::suspend(fn () => $this->em->flush());

        if (!empty($previous)) {
            $this->removeOrphanUnknowns($list, array_keys($previous));
        }

        return $count;
    }

    /**
     * Replays the recognition of every face no user named, oldest first, against the faces
     * already processed: after a change of threshold, or to group faces detected before.
     *
     * @return int the number of faces that changed person
     */
    public function relink(IntegrationConfig $config): int
    {
        $threshold = (float) $config['matchThreshold'];
        $definition = $this->configResolver->getDefinition($config);
        $list = $this->configResolver->getEntityList($config);

        // The references (faces users named) are known first, whatever their age: a face named
        // yesterday must recognize the faces detected the day before
        $processed = [];
        foreach ($this->attributeRepository->iterateEmbeddings($definition->getId()) as $row) {
            if (Attribute::ORIGIN_HUMAN === $row['origin']) {
                $processed[] = $row;
            }
        }

        $count = 0;
        $previous = [];
        foreach ($this->attributeRepository->iterateEmbeddings($definition->getId()) as $row) {
            if (Attribute::ORIGIN_HUMAN !== $row['origin']) {
                $match = $this->findPerson($row['vector'], $processed, $threshold);
                $value = $match['value'] ?? null;
                if (null === $value) {
                    $person = $this->createUnknownPerson($config);
                    $this->em->flush();
                    $value = $person->getId();
                }
                if ($value !== $row['value']) {
                    $previous[$row['value']] = true;
                    $this->em->createQueryBuilder()
                        ->update(Attribute::class, 'a')
                        ->set('a.value', ':value')
                        ->set('a.confidence', ':confidence')
                        ->andWhere('a.id = :id')
                        ->setParameter('value', $value)
                        ->setParameter('confidence', $match['confidence'] ?? $row['confidence'])
                        ->setParameter('id', $row['id'])
                        ->getQuery()
                        ->execute();
                    $this->deferredIndexListener->scheduleForUpdate($this->em->getReference(Asset::class, $row['asset']));
                    ++$count;
                }
                $row['value'] = $value;
                $processed[] = $row;
            }
        }

        if (!empty($previous)) {
            $this->removeOrphanUnknowns($list, array_keys($previous));
        }

        return $count;
    }

    private function isPendingPerson(string $entityId): bool
    {
        if (!isset($this->pendingPersons[$entityId])) {
            $entity = $this->em->find(AttributeEntity::class, $entityId);
            $this->pendingPersons[$entityId] = $entity instanceof AttributeEntity && AttributeEntity::STATUS_PENDING === $entity->getStatus();
        }

        return $this->pendingPersons[$entityId];
    }
}
