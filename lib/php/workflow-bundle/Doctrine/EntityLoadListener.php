<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Doctrine;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use Alchemy\Workflow\State\JobState;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

final class EntityLoadListener implements EventSubscriberInterface
{
    private string $workflowStateEntity;
    private string $jobStateEntity;

    public function __construct(
        string $workflowStateEntity,
        string $jobStateEntity,
    ) {
        $this->workflowStateEntity = $workflowStateEntity;
        $this->jobStateEntity = $jobStateEntity;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $class = $args->getClassMetadata();

        if (WorkflowState::class === $class->getName()) {
            if (WorkflowState::class !== $this->workflowStateEntity) {
                $class->isMappedSuperclass = true;
            }
        }

        if ($this->workflowStateEntity === $class) {
            $class->mapOneToMany([
                'fieldName' => 'jobs',
                'targetEntity' => $this->jobStateEntity,
                'mappedBy' => 'workflow',
            ]);
        }

        if (JobState::class === $class->getName()) {
            if (JobState::class !== $this->jobStateEntity) {
                $class->isMappedSuperclass = true;
            }

            $class->mapManyToOne([
                'fieldName' => 'workflow',
                'targetEntity' => $this->workflowStateEntity,
                'inversedBy' => 'jobs',
                'joinColumn' => [
                    'name' => 'workflow_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ],
            ]);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
        ];
    }
}
