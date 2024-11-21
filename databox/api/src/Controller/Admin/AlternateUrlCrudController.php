<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\AlternateUrl;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AlternateUrlCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AlternateUrl::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Alternate URL')
            ->setEntityLabelInPlural('Alternate URLs')
            ->setSearchFields(['id', 'type', 'label']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('type'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace');
        yield TextField::new('type');
        yield TextField::new('label');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
