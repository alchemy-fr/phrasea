<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionPropagate;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Turns the generic edits of the attributes and the entities into face recognition decisions:
 * a user changing the person of a face names it (the face becomes a reference, the decision is
 * propagated), a user approving a pending person makes its best face a reference, and the unknown
 * persons that no face references any more are removed.
 */
#[AsDoctrineListener(Events::onFlush)]
final class FaceRecognitionDoctrineListener
{
    private static bool $enabled = true;

    public function __construct(
        private readonly FaceRecognitionConfigResolver $configResolver,
        private readonly FaceManager $faceManager,
        private readonly PostFlushStack $postFlushStack,
        private readonly Security $security,
    ) {
    }

    /**
     * The recognition writes values itself (propagation, relink): those are not user decisions.
     */
    public static function suspend(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;
        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!self::$enabled) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $userId = $this->getUserId();

        /** @var array<string, array{0: \App\Integration\IntegrationConfig, 1: string[]}> $orphanCandidates */
        $orphanCandidates = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Attribute && null !== $entity->getEmbedding()) {
                $changeSet = $uow->getEntityChangeSet($entity);
                if (!isset($changeSet['value']) || null === $userId) {
                    continue;
                }
                $config = $this->configResolver->getConfigOfDefinition($entity->getDefinition());
                if (null === $config) {
                    continue;
                }
                [$previous] = $changeSet['value'];
                $this->faceManager->confirm($entity, $userId);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Attribute::class), $entity);
                $orphanCandidates[$config->getIntegrationId()] ??= [$config, []];
                $orphanCandidates[$config->getIntegrationId()][1][] = (string) $previous;
                $this->postFlushStack->addBusMessage(new FaceRecognitionPropagate($entity->getId(), $config->getIntegrationId()));
            } elseif ($entity instanceof AttributeEntity) {
                $changeSet = $uow->getEntityChangeSet($entity);
                if (!isset($changeSet['status']) || AttributeEntity::STATUS_APPROVED !== $entity->getStatus()) {
                    continue;
                }
                $config = $this->configResolver->getConfigOfList($entity->getList());
                if (null === $config) {
                    continue;
                }
                $this->postFlushStack->addCallback(function () use ($entity, $config, $userId): void {
                    $reference = $this->faceManager->markReference($entity, $config, $userId);
                    if (null !== $reference) {
                        $this->postFlushStack->addBusMessage(new FaceRecognitionPropagate($reference->getId(), $config->getIntegrationId()));
                    }
                });
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Attribute && null !== $entity->getEmbedding()) {
                $config = $this->configResolver->getConfigOfDefinition($entity->getDefinition());
                if (null !== $config) {
                    $orphanCandidates[$config->getIntegrationId()] ??= [$config, []];
                    $orphanCandidates[$config->getIntegrationId()][1][] = (string) $entity->getValue();
                }
            }
        }

        foreach ($orphanCandidates as [$config, $values]) {
            $this->postFlushStack->addCallback(function () use ($config, $values): void {
                $this->faceManager->removeOrphanUnknowns($this->configResolver->getEntityList($config), $values);
            });
        }
    }

    private function getUserId(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof JwtUser ? $user->getId() : null;
    }
}
