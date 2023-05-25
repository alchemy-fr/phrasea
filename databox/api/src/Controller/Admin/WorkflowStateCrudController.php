<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\ArrayObjectField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Workflow\WorkflowState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkflowStateCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly string $databoxClientBaseUrl)
    {
    }

    public static function getEntityFqcn(): string
    {
        return WorkflowState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewWorkflow = Action::new('viewWorkflow', 'View', 'fa fa-eye')
            ->setHtmlAttributes(['target' => '_blank'])
            ->linkToUrl(fn (WorkflowState $entity): string => sprintf('%s/workflows/%s', $this->databoxClientBaseUrl, $entity->getId()));

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewWorkflow)
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
        $eventName = TextField::new('eventName', 'Event');
        $eventInputs = ArrayObjectField::new('eventInputs', 'Event inputs');
        $initiator = TextField::new('initiatorId', 'Initiator');
        $context = ArrayObjectField::new('context', 'Context');
        $asset = AssociationField::new('asset', 'Asset');
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
            return [
                $id,
                $status,
                $name,
                $eventName,
                $initiator,
                $asset,
                $duration,
                $startedAt,
                $endedAt,
            ];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $asset, $name, $startedAt, $status, $endedAt, $duration, $eventName, $eventInputs, $context];
        }

        return [];
    }
}
