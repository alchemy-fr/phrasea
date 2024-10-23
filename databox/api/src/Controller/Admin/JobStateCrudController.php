<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\ArrayObjectField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\Workflow\Doctrine\Entity\JobState;
use Alchemy\Workflow\State\JobState as JobStateModel;
use Alchemy\Workflow\State\JobState as ModelJobState;
use Alchemy\Workflow\WorkflowOrchestrator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;

class JobStateCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly WorkflowOrchestrator $workflowOrchestrator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return JobState::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $retry = Action::new('retryJob', 'Retry job', 'fas fa-wrench')
            ->displayIf(fn (JobState $entity) => JobStateModel::STATUS_FAILURE === $entity->getStatus())
            ->linkToCrudAction('retryJob');

        $cancel = Action::new('cancelJob', 'Cancel job', 'fas fa-ban')
            ->displayIf(fn (JobState $entity) => JobStateModel::STATUS_RUNNING === $entity->getStatus())
            ->linkToCrudAction('cancelJob');

        $rerun = Action::new('rerunJob', 'Rerun job', 'fas fa-wrench')
            ->displayIf(fn (JobState $entity) => JobStateModel::STATUS_FAILURE !== $entity->getStatus())
            ->linkToCrudAction('rerunJob');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $retry)
            ->add(Crud::PAGE_INDEX, $rerun)
            ->add(Crud::PAGE_INDEX, $cancel)
        ;
    }

    public function retryJob(AdminContext $context): RedirectResponse
    {
        /** @var JobState $jobState */
        $jobState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->retryFailedJobs($jobState->getWorkflow()->getId(), $jobState->getJobState()->getJobId());

        return $this->returnToReferer($context);
    }

    public function cancelJob(AdminContext $context): RedirectResponse
    {
        /** @var JobState $jobState */
        $jobState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->cancelWorkflow($jobState->getWorkflow()->getId());

        return $this->returnToReferer($context);
    }

    public function rerunJob(AdminContext $context): RedirectResponse
    {
        /** @var JobState $jobState */
        $jobState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->rerunJobs($jobState->getWorkflow()->getId(), $jobState->getJobState()->getJobId());

        return $this->returnToReferer($context);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Job State')
            ->setEntityLabelInPlural('Job States')
            ->setSearchFields(['id']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices([
                'TRIGGERED' => ModelJobState::STATUS_TRIGGERED,
                'SUCCESS' => ModelJobState::STATUS_SUCCESS,
                'FAILURE' => ModelJobState::STATUS_FAILURE,
                'SKIPPED' => ModelJobState::STATUS_SKIPPED,
                'RUNNING' => ModelJobState::STATUS_RUNNING,
            ]))
            ->add(DateTimeFilter::new('startedAt'))
            ->add(DateTimeFilter::new('endedAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workflow', 'Workflow');
        yield TextField::new('jobId', 'Job ID');
        yield DateTimeField::new('triggeredAt', 'Triggered At');
        yield DateTimeField::new('startedAt', 'Started At');
        yield ChoiceField::new('status', 'Status')
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

        yield DateTimeField::new('endedAt', 'Ended At');
        yield TextField::new('durationString', 'Duration');
        yield ArrayField::new('errors')
            ->hideOnIndex();
        yield ArrayObjectField::new('inputs')
            ->hideOnIndex();
        yield ArrayObjectField::new('outputs')
            ->hideOnIndex();
    }
}
