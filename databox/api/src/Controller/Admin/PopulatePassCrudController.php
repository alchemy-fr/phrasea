<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\Search\ESPopulate;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class PopulatePassCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PopulatePass::class;
    }

    public const string CSRF_TOKEN_ID = 'populate_pass_add';

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MessageBusInterface $bus,
        private readonly IndexManager $indexManager,
        private readonly EntityManagerInterface $em,
        private readonly PopulateLockManager $lockManager,
    ) {
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $globalAddPopulateAction = Action::new('AddPopulate', 'Add Populate', 'fa fa-play')
            ->linkToCrudAction('addPopulate')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $globalAddPopulateAction)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Populate Pass')
            ->setEntityLabelInPlural('Populate Passes')
            ->setSearchFields(['id', 'documentCount', 'progress', 'indexName', 'mapping', 'error'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('documentCount'))
            ->add(TextFilter::new('indexName'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('endedAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('indexName');
        yield TextareaField::new('progressString')
            ->onlyOnIndex();
        yield TextareaField::new('timeTakenUnit')
            ->onlyOnIndex();
        yield DateTimeField::new('endedAt');
        yield IntegerField::new('documentCount')
            ->hideOnForm();
        yield IntegerField::new('progress')
            ->hideOnIndex();
        yield BooleanField::new('successful')
            ->renderAsSwitch(false)
            ->onlyOnIndex();
        yield TextField::new('error');
        yield JsonField::new('mapping')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();

    }

    /**
     * Shows the "which indices?" form (GET), then queues one populate per selected index (POST).
     */
    #[AdminRoute('/add', name: 'add_populate')]
    public function addPopulate(Request $request): Response
    {
        $indices = array_keys($this->indexManager->getAllIndexes());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('token'))) {
                $this->addFlash('danger', 'Invalid CSRF token, nothing was done. Please retry.');

                return $this->redirect($this->getIndexUrl());
            }

            $running = $this->getRunningIndices($indices);

            if ('all' === $request->request->get('scope', 'all')) {
                if ([] !== $running) {
                    $this->addFlash('danger', \sprintf('A populate is still running for: %s. Wait for it to finish, or delete its pass if the worker died.', implode(', ', $running)));

                    return $this->redirect($this->getIndexUrl());
                }

                $this->bus->dispatch(new ESPopulate());
                $this->addFlash('info', 'Populate of all indices was triggered');

                return $this->redirect($this->getIndexUrl());
            }

            $selected = array_values(array_unique(array_map(strval(...), $request->request->all('indices'))));
            if ([] === $selected) {
                $this->addFlash('danger', 'Select at least one index, or choose "All indices".');

                return $this->redirect($this->getIndexUrl());
            }
            $unknown = array_diff($selected, $indices);
            if ([] !== $unknown) {
                $this->addFlash('danger', \sprintf('Unknown index(es): %s.', implode(', ', $unknown)));

                return $this->redirect($this->getIndexUrl());
            }

            $busy = array_intersect($selected, $running);
            if ([] !== $busy) {
                $this->addFlash('danger', \sprintf('A populate is still running for: %s. Wait for it to finish, or delete its pass if the worker died.', implode(', ', $busy)));

                return $this->redirect($this->getIndexUrl());
            }

            // one message per index: passes run independently and can be spread across workers
            foreach ($selected as $index) {
                $this->bus->dispatch(new ESPopulate($index));
            }
            $this->addFlash('info', \sprintf('Populate of %d index(es) was triggered: %s', \count($selected), implode(', ', $selected)));

            return $this->redirect($this->getIndexUrl());
        }

        return $this->render('admin/populate_pass/add.html.twig', [
            'indices' => $indices,
            'running' => $this->getRunningIndices($indices),
            'indexUrl' => $this->getIndexUrl(),
        ]);
    }

    /**
     * Force-releases the populate lock of an index (and closes its open passes)
     * when a worker died mid-populate.
     */
    #[AdminRoute('/unlock/{index}', name: 'unlock', options: ['methods' => ['POST']])]
    public function unlockPopulate(Request $request, string $index): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Invalid CSRF token, nothing was done. Please retry.');

            return $this->redirect($this->getIndexUrl());
        }
        if (!\in_array($index, array_keys($this->indexManager->getAllIndexes()), true)) {
            $this->addFlash('danger', \sprintf('Unknown index "%s".', $index));

            return $this->redirect($this->getIndexUrl());
        }

        $result = $this->lockManager->forceRelease($index);

        $this->addFlash('warning', \sprintf(
            'Populate lock of "%s" %s, %d open pass(es) closed. The index can be populated again.',
            $index,
            $result['lockReleased'] ? 'released' : 'was already free',
            $result['passesClosed'],
        ));

        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Logical indices that must not be populated right now: an unterminated pass or a held lock.
     *
     * @param list<string> $indices
     *
     * @return list<string>
     */
    private function getRunningIndices(array $indices): array
    {
        $running = $this->lockManager->getLockedIndices($indices);
        foreach ($this->em->getRepository(PopulatePass::class)->findBy(['endedAt' => null]) as $pass) {
            $running[] = $pass->getIndexName();
        }
        $running = array_values(array_unique($running));
        sort($running);

        return $running;
    }

    private function getIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(PopulatePassCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();
    }
}
