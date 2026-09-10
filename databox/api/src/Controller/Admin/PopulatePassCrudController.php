<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\Search\ESPopulate;
use App\Entity\Admin\PopulatePass;
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
     * Shows the "which index?" form (GET), then queues the populate (POST).
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

            $index = $request->request->get('index');
            $index = '' === $index || null === $index ? null : (string) $index;
            if (null !== $index && !\in_array($index, $indices, true)) {
                $this->addFlash('danger', \sprintf('Unknown index "%s".', $index));

                return $this->redirect($this->getIndexUrl());
            }

            $this->bus->dispatch(new ESPopulate($index));

            $this->addFlash('info', null === $index
                ? 'Populate of all indices was triggered'
                : \sprintf('Populate of index "%s" was triggered', $index));

            return $this->redirect($this->getIndexUrl());
        }

        return $this->render('admin/populate_pass/add.html.twig', [
            'indices' => $indices,
            'indexUrl' => $this->getIndexUrl(),
        ]);
    }

    private function getIndexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(PopulatePassCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();
    }
}
