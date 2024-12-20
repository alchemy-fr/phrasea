<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\Search\ESPopulate;
use App\Entity\Admin\PopulatePass;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class PopulatePassCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PopulatePass::class;
    }

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalAddPopulateAction = Action::new('AddPopulate')
            ->linkToCrudAction('addPopulate')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $globalAddPopulateAction)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Populate Pass')
            ->setEntityLabelInPlural('Populate Passes')
            ->setSearchFields(['id', 'documentCount', 'progress', 'indexName', 'mapping', 'error'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('documentCount'))
            ->add(TextFilter::new('indexName'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('endedAt'))
        ;
    }

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

    public function addPopulate(): Response
    {
        $this->bus->dispatch(new ESPopulate());

        $this->addFlash('info', 'Populate command was triggered');

        $url = $this->adminUrlGenerator
            ->setController(PopulatePassCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
