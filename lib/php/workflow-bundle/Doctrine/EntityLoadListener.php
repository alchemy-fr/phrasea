<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Doctrine;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use Alchemy\Workflow\State\JobState;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::loadClassMetadata)]
final readonly class EntityLoadListener
{
    public function __construct(
        private string $workflowStateEntity,
        private string $jobStateEntity,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $class = $args->getClassMetadata();
        $name = $class->getName();

        if (WorkflowState::class === $name) {
            if (WorkflowState::class !== $this->workflowStateEntity) {
                $class->isMappedSuperclass = true;
            }
        }

        if (JobState::class === $name) {
            if (JobState::class !== $this->jobStateEntity) {
                $class->isMappedSuperclass = true;
            }
        }

        if ($this->jobStateEntity === $name) {
            $class->mapManyToOne([
                'fieldName' => 'workflow',
                'targetEntity' => $this->workflowStateEntity,
                'joinColumns' => [
                    [
                        'name' => 'workflow_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'CASCADE',
                        'nullable' => false,
                    ],
                ],
            ]);
        }
    }
}
