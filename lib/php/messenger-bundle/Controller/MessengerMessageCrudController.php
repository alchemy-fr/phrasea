<?php

namespace Alchemy\MessengerBundle\Controller;

use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\AdminVoter;
use Alchemy\MessengerBundle\Entity\MessengerMessage;
use Alchemy\MessengerBundle\Purge\FailedMessagePurger;
use Alchemy\MessengerBundle\Retry\FailedMessageRetrier;
use Alchemy\MessengerBundle\Retry\RetryReport;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("'.AdminVoter::ROLE.'") or is_granted("'.JwtUser::ROLE_TECH.'")'))]
class MessengerMessageCrudController extends AbstractCrudController
{
    private const string PURGE_CSRF_TOKEN_ID = 'messenger-purge-all';

    public function __construct(
        private readonly FailedMessageRetrier $retrier,
        private readonly FailedMessagePurger $purger,
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $retry = Action::new('retry', 'Retry', 'fas fa-refresh')
            ->displayIf(fn (MessengerMessage $entity) => !$entity->wasRetried())
            ->linkToCrudAction('retry');

        $batchRetry = Action::new('batchRetry', 'Retry', 'fas fa-refresh')
            ->linkToCrudAction('batchRetry');

        $retryAll = Action::new('retryAll', 'Retry all', 'fas fa-refresh')
            ->linkToCrudAction('retryAll')
            ->createAsGlobalAction()
            ->renderAsForm()
            ->askConfirmation('Retry every message that was not retried yet?', 'Retry all');

        $purgeAll = Action::new('purgeAll', 'Purge all', 'fas fa-trash')
            ->linkToCrudAction('purgeAll')
            ->createAsGlobalAction()
            ->renderAsForm()
            ->asDangerAction()
            ->askConfirmation('Delete every message of the table, retried or not? A second step will ask you to confirm.', 'Continue');

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, $retry)
            ->add(Crud::PAGE_DETAIL, $retry)
            ->addBatchAction($batchRetry)
            ->add(Crud::PAGE_INDEX, $retryAll)
            ->add(Crud::PAGE_INDEX, $purgeAll)
        ;
    }

    #[AdminRoute(path: '/messenger-message/retry')]
    public function retry(AdminContext $context): RedirectResponse
    {
        /** @var MessengerMessage $message */
        $message = $context->getEntity()->getInstance();

        try {
            if (!$this->retrier->retry($message->getId(), $message->getBody(), $message->getDecodedHeaders() ?? [])) {
                $this->addFlash('warning', 'Message already retried');
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Message could not be retried: %s', $e->getMessage()));
        }

        return $this->redirect($this->getIndexUrl());
    }

    #[AdminRoute(path: '/batch-retry', name: 'batch_retry', options: ['methods' => ['POST']])]
    public function batchRetry(AdminContext $context, BatchActionDto $batchActionDto): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('ea-batch-action-batchRetry-'.MessengerMessage::class, $batchActionDto->getCsrfToken())) {
            $this->addFlash('danger', 'Invalid CSRF token');

            return $this->redirect($this->getIndexUrl());
        }

        if ($batchActionDto->getEntityFqcn() !== $context->getEntity()->getFqcn()) {
            throw new BadRequestHttpException();
        }

        $this->flashReport($this->retrier->retryIds($batchActionDto->getEntityIds()));

        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Retries the whole table, whatever the current search: every message not retried yet.
     */
    #[AdminRoute(path: '/retry-all', name: 'retry_all', options: ['methods' => ['POST']])]
    public function retryAll(): RedirectResponse
    {
        $this->flashReport($this->retrier->retryAll());

        return $this->redirect($this->getIndexUrl());
    }

    /**
     * First step of the purge: the whole table, whatever the current search, retried or not.
     * The current max id bounds what the second step deletes.
     */
    #[AdminRoute(path: '/purge-all', name: 'purge_all', options: ['methods' => ['POST']])]
    public function purgeAll(): Response
    {
        $maxId = $this->purger->getMaxId();
        if (null === $maxId) {
            $this->addFlash('info', 'No message to delete');

            return $this->redirect($this->getIndexUrl());
        }

        return $this->render('@AlchemyMessenger/admin/purge_all.html.twig', [
            'count' => $this->purger->countUpTo($maxId),
            'maxId' => $maxId,
            'confirmUrl' => $this->container->get(AdminUrlGeneratorInterface::class)
                ->setController(static::class)
                ->setAction('purgeAllConfirm')
                ->unset(EA::ENTITY_ID)
                ->generateUrl(),
            'cancelUrl' => $this->getIndexUrl(),
            'csrfTokenId' => self::PURGE_CSRF_TOKEN_ID,
        ]);
    }

    #[AdminRoute(path: '/purge-all/confirm', name: 'purge_all_confirm', options: ['methods' => ['POST']])]
    public function purgeAllConfirm(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::PURGE_CSRF_TOKEN_ID, (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Invalid CSRF token');

            return $this->redirect($this->getIndexUrl());
        }

        $maxId = $request->request->get('maxId');
        if (!is_numeric($maxId)) {
            throw new BadRequestHttpException('Invalid maxId');
        }
        $maxId = (int) $maxId;

        $count = $this->purger->countUpTo($maxId);
        if (trim((string) $request->request->get('confirmation')) !== (string) $count) {
            $this->addFlash('danger', sprintf('Confirmation does not match the %d message(s) to delete: nothing was deleted', $count));

            return $this->redirect($this->getIndexUrl());
        }

        $this->addFlash('success', sprintf('%d message(s) deleted', $this->purger->purgeUpTo($maxId)));

        return $this->redirect($this->getIndexUrl());
    }

    private function flashReport(RetryReport $report): void
    {
        if ($report->isEmpty()) {
            $this->addFlash('info', 'No message to retry');

            return;
        }

        if ($report->retried > 0 || $report->skipped > 0) {
            $message = sprintf('%d message(s) retried', $report->retried);
            if ($report->skipped > 0) {
                $message .= sprintf(', %d already retried', $report->skipped);
            }
            $this->addFlash($report->retried > 0 ? 'success' : 'warning', $message);
        }

        if ($report->failed > 0) {
            $this->addFlash('danger', sprintf('%d message(s) could not be retried: %s', $report->failed, $report->firstError));
        }

        if (null !== $report->stoppedBy) {
            $this->addFlash('danger', sprintf('Retry stopped: %s', $report->stoppedBy));
        }
    }

    private function getIndexUrl(): string
    {
        return $this->container->get(AdminUrlGeneratorInterface::class)
            ->setController(static::class)
            ->setAction(Action::INDEX)
            ->unset(EA::ENTITY_ID)
            ->generateUrl();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('queueName', 'Queue'))
            ->add(TextFilter::new('headers', 'Type'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('availableAt'))
            ->add(NullFilter::new('deliveredAt', 'Retried')->setChoiceLabels('Not retried', 'Retried'))
        ;
    }

    public static function getEntityFqcn(): string
    {
        return MessengerMessage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            DateTimeField::new('createdAt'),
            TextField::new('queueName', 'Queue'),
            JsonField::new('body')
                ->hideOnIndex(),
            CodeField::new('type'),
            TextField::new('error'),
            JsonField::new('headers')
                ->hideOnIndex(),
            DateTimeField::new('deliveredAt'),
            DateTimeField::new('availableAt'),
        ];
    }
}
