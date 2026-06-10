<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Elasticsearch\AssetIndexer;
use App\Entity\Admin\AssetIndexPass;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

class AssetIndexPassCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly AssetIndexer $assetIndexer,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AssetIndexPass::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalAssetIndexAction = Action::new('Index Assets')
            ->linkToCrudAction('assetIndex')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, $globalAssetIndexAction)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AssetIndex Pass')
            ->setEntityLabelInPlural('AssetIndex Passes')
            ->setSearchFields(['id', 'documentCount', 'progress'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
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
        yield DateTimeField::new('createdAt')
            ->hideOnForm();

    }

    public function assetIndex(): Response
    {
        $this->assetIndexer->index(new NullOutput());
        $this->addFlash('info', 'Asset indexing has been triggered');

        $url = $this->adminUrlGenerator
            ->setController(AssetIndexPassCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
