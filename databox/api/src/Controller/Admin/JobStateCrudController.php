<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\Doctrine\Entity\JobState;
use Alchemy\Workflow\State\JobState as ModelJobState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JobStateCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return JobState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Job State')
            ->setEntityLabelInPlural('Job States')
            ->setSearchFields(['id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();

        $workflowName = TextField::new('workflow.name', 'Workflow Name');
        $job = TextField::new('jobId', 'Job ID');
        $status = ChoiceField::new('status', 'Status')
            ->setChoices([
                'TRIGGERED' => ModelJobState::STATUS_TRIGGERED,
                'SUCCESS' => ModelJobState::STATUS_SUCCESS,
                'FAILURE' => ModelJobState::STATUS_FAILURE,
                'SKIPPED' => ModelJobState::STATUS_SKIPPED,
                'RUNNING' => ModelJobState::STATUS_RUNNING,
            ])
            ->renderAsBadges([
                ModelJobState::STATUS_TRIGGERED => 'secondary',
                ModelJobState::STATUS_SUCCESS => 'success',
                ModelJobState::STATUS_FAILURE => 'danger',
                ModelJobState::STATUS_SKIPPED => 'info',
                ModelJobState::STATUS_RUNNING => 'warning',
            ]);
        $triggeredAt = DateTimeField::new('triggeredAt', 'Triggered At');
        $startedAt = DateTimeField::new('startedAt', 'Started At');
        $endedAt = DateTimeField::new('endedAt', 'Ended At');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workflowName, $job, $triggeredAt, $startedAt, $status, $endedAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $workflowName, $job, $triggeredAt, $startedAt, $status, $endedAt];
        }

        return [];
    }
}
