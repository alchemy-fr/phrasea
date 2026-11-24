<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\ArrayObjectField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Workflow\WorkflowState;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WorkflowStateCrudController extends AbstractAdminCrudController
{
    private const array STATUS_CHOICES = [
        'STARTED' => ModelWorkflowState::STATUS_STARTED,
        'SUCCESS' => ModelWorkflowState::STATUS_SUCCESS,
        'FAILURE' => ModelWorkflowState::STATUS_FAILURE,
        'CANCELLED' => ModelWorkflowState::STATUS_CANCELLED,
    ];

    public function __construct(
        private readonly string $databoxClientBaseUrl,
        private readonly WorkflowOrchestrator $workflowOrchestrator,
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return WorkflowState::class;
    }

    #[AdminRoute(path: '/workflow-state/{id}/cancel', name: 'cancelWorkflow')]
    public function cancelWorkflow(AdminContext $context): RedirectResponse
    {
        /** @var WorkflowState $workflowState */
        $workflowState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->cancelWorkflow($workflowState->getId());

        return $this->returnToReferer($context);
    }

    #[\Override]
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

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workflow State')
            ->setEntityLabelInPlural('Workflow States')
            ->setDefaultSort([
                'startedAt' => 'DESC',
            ])
            ->setSearchFields(['id', 'name']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES))
            ->add(TextFilter::new('name'))
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add($this->userChoiceFilter->createFilter('initiatorId', 'Initiator'))
            ->add(DateTimeFilter::new('startedAt'))
            ->add(DateTimeFilter::new('endedAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield ChoiceField::new('status', 'Status')
            ->setChoices(self::STATUS_CHOICES)
            ->renderAsBadges([
                ModelWorkflowState::STATUS_STARTED => 'warning',
                ModelWorkflowState::STATUS_SUCCESS => 'success',
                ModelWorkflowState::STATUS_FAILURE => 'danger',
                ModelWorkflowState::STATUS_CANCELLED => 'dark',
            ]);

        yield TextField::new('name', 'Name');
        yield TextField::new('eventName', 'Event');
        yield ArrayObjectField::new('eventInputs', 'Event inputs')
            ->hideOnIndex();
        yield $this->userChoiceField->create('initiatorId', 'Initiator');
        yield ArrayObjectField::new('context', 'Context')
            ->hideOnIndex();
        yield AssociationField::new('asset', 'Asset')
            ->autocomplete();
        yield TextField::new('durationString', 'Duration');
        yield DateTimeField::new('startedAt', 'Started At');
        yield DateTimeField::new('endedAt', 'Ended At');
    }
}
