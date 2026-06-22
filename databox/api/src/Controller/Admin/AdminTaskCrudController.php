<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\Search\IndexAssets;
use App\Entity\Admin\AdminTask;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class AdminTaskCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AdminTask::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Admin Task')
            ->setEntityLabelInPlural('Admin Tasks')
            ->setSearchFields(['id', 'task', 'name', 'payload'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('task');
        yield JsonField::new('payload')
            ->hideOnIndex()
        ;
        yield TextareaField::new('output')
            ->hideOnIndex()
        ;
        yield TextareaField::new('progressString', 'Progress')
            ->onlyOnIndex();
        yield TextareaField::new('timeTakenUnit')
            ->onlyOnIndex();
        yield TextareaField::new('estimated');
        yield TextareaField::new('remaining');
        yield DateTimeField::new('endedAt');
        yield IntegerField::new('progress')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield BooleanField::new('successful')
            ->renderAsSwitch(false)
            ->onlyOnIndex();

    }

    public function assetIndex(): Response
    {
        $this->bus->dispatch(new IndexAssets());
        $this->addFlash('info', 'Asset and Attributes indexing has been triggered');

        $url = $this->adminUrlGenerator
            ->setController(AdminTaskCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
