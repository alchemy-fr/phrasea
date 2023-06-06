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
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
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

        $rerun = Action::new('rerunJob', 'Rerun job', 'fas fa-wrench')
            ->displayIf(fn (JobState $entity) => JobStateModel::STATUS_FAILURE !== $entity->getStatus())
            ->linkToCrudAction('rerunJob');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $retry)
            ->add(Crud::PAGE_INDEX, $rerun)
        ;
    }

    public function retryJob(AdminContext $context)
    {
        /** @var JobState $jobState */
        $jobState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->retryFailedJobs($jobState->getWorkflow()->getId(), $jobState->getJobState()->getJobId());

        return new RedirectResponse($context->getReferrer());
    }

    public function rerunJob(AdminContext $context)
    {
        /** @var JobState $jobState */
        $jobState = $context->getEntity()->getInstance();
        $this->workflowOrchestrator->rerunJobs($jobState->getWorkflow()->getId(), $jobState->getJobState()->getJobId());

        return new RedirectResponse($context->getReferrer());
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
        $duration = TextField::new('durationString', 'Duration');
        $job = TextField::new('jobId', 'Job ID');
        $errors = ArrayField::new('errors', 'Errors');
        $outputs = ArrayObjectField::new('outputs', 'Outputs');
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
            return [$id, $workflowName, $job, $triggeredAt, $startedAt, $status, $endedAt, $duration];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $workflowName, $job, $triggeredAt, $startedAt, $status, $outputs, $errors, $endedAt, $duration];
        }

        return [];
    }
}
