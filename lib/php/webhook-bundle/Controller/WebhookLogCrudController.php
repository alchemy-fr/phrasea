<?php

namespace Alchemy\WebhookBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

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
            ->add(TextFilter::new('event'))
            ->add(EntityFilter::new('webhook')->setFormTypeOption('value_type_options.choice_label', 'url'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->onlyOnDetail();
        yield TextField::new('event');
        yield JsonField::new('payload')
            ->onlyOnDetail();
        yield TextareaField::new('response')
            ->hideOnIndex();
        yield TextField::new('webhook.url', 'URL')
            ->onlyOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield AssociationField::new('webhook')
            ->autocomplete()
            ->hideOnIndex();
    }
}
