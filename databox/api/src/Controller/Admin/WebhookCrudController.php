<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\WebhookBundle\Entity\Webhook;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Alchemy\WebhookBundle\Field\EventsChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class WebhookCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Webhook::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'url', 'secret', 'events', 'options']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('active'))
            ->add(TextFilter::new('url'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->onlyOnDetail();
        yield TextField::new('url', 'URL');
        yield BooleanField::new('active');   
        yield EventsChoiceField::new('events')
            ->hideOnIndex();
        yield BooleanField::new('verifySSL', 'Verify SSL')
            ->hideOnIndex();
        yield TextField::new('secret')
            ->hideOnIndex();
        yield Field::new('timeout')
            ->onlyOnForms();
        yield TextField::new('options')
            ->onlyOnDetail();   
        yield TextareaField::new('eventsLabel')
            ->onlyOnIndex();    
        yield DateTimeField::new('createdAt')
            ->hideOnForm();           

    }
}
