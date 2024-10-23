<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\ArrayObjectField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Workflow\WorkflowState;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WorkflowStateCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly string $databoxClientBaseUrl,
        private readonly WorkflowOrchestrator $workflowOrchestrator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return WorkflowState::class;
    }

    public function cancelWorkflow(AdminContext $context): RedirectResponse
    {
        /** @var WorkflowState $workflowState */
        $workflowState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->cancelWorkflow($workflowState->getId());

        return $this->returnToReferer($context);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewWorkflow = Action::new('viewWorkflow', 'View', 'fa fa-eye')
            ->setHtmlAttributes(['target' => '_blank'])
            ->linkToUrl(fn (WorkflowState $entity): string => sprintf('%s/workflows/%s', $this->databoxClientBaseUrl, $entity->getId()));

        $cancel = Action::new('cancelWorkflow', 'Cancel Workflow', 'fas fa-ban')
            ->displayIf(fn (WorkflowState $entity) => ModelWorkflowState::STATUS_STARTED === $entity->getStatus())
            ->linkToCrudAction('cancelWorkflow');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $viewWorkflow)
            ->add(Crud::PAGE_INDEX, $cancel)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workflow State')
            ->setEntityLabelInPlural('Workflow States')
            ->setSearchFields(['id']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'STARTED' => ModelWorkflowState::STATUS_STARTED,
                'SUCCESS' => ModelWorkflowState::STATUS_SUCCESS,
                'FAILURE' => ModelWorkflowState::STATUS_FAILURE,
            ]))
            ->add(DateTimeFilter::new('startedAt'))
            ->add(DateTimeFilter::new('endedAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield ChoiceField::new('status', 'Status')
            ->setChoices([
                'STARTED' => ModelWorkflowState::STATUS_STARTED,
                'SUCCESS' => ModelWorkflowState::STATUS_SUCCESS,
                'FAILURE' => ModelWorkflowState::STATUS_FAILURE,
            ])->renderAsBadges([
                ModelWorkflowState::STATUS_STARTED => 'warning',
                ModelWorkflowState::STATUS_SUCCESS => 'success',
                ModelWorkflowState::STATUS_FAILURE => 'danger',
            ]);

        yield TextField::new('name', 'Name');
        yield TextField::new('eventName', 'Event');
        yield ArrayObjectField::new('eventInputs', 'Event inputs')
            ->hideOnIndex();
        yield TextField::new('initiatorId', 'Initiator');
        yield ArrayObjectField::new('context', 'Context')
            ->hideOnIndex();
        yield AssociationField::new('asset', 'Asset');
        yield TextField::new('durationString', 'Duration');
        yield DateTimeField::new('startedAt', 'Started At');
        yield DateTimeField::new('endedAt', 'Ended At');
    }
}
