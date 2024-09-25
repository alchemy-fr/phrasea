<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class WebhookLogCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebhookLog::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'event', 'payload', 'response']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->onlyOnDetail();
        yield TextField::new('event')
            ->hideOnIndex();
        yield TextField::new('payload')
            ->onlyOnDetail();  
        yield TextareaField::new('response')
            ->hideOnIndex();
        yield TextareaField::new('webhook.url', 'URL')
            ->onlyOnIndex();    
        yield DateTimeField::new('createdAt');
        yield AssociationField::new('webhook')
            ->hideOnIndex();          
    }
}
