<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;

class WorkflowStateCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkflowState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workflow State')
            ->setEntityLabelInPlural('Workflow States')
            ->setSearchFields(['id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();
        $name = TextField::new('name', 'Name');
        $event = TextField::new('workflowState.event.name', 'Event');
        $duration = TextField::new('durationString', 'Duration');
        $startedAt = DateTimeField::new('startedAt', 'Started At');
        $endedAt = DateTimeField::new('endedAt', 'Ended At');
        $status = ChoiceField::new('status', 'Status')
            ->setChoices([
                'STARTED' => ModelWorkflowState::STATUS_STARTED,
                'SUCCESS' => ModelWorkflowState::STATUS_SUCCESS,
                'FAILURE' => ModelWorkflowState::STATUS_FAILURE,
            ])->renderAsBadges([
                ModelWorkflowState::STATUS_STARTED => 'warning',
                ModelWorkflowState::STATUS_SUCCESS => 'success',
                ModelWorkflowState::STATUS_FAILURE => 'danger',
            ]);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $startedAt, $status, $endedAt, $duration, $event];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $startedAt, $status, $endedAt, $duration, $event];
        }

        return [];
    }
}
