<?php

namespace App\Controller\Admin;

use App\Entity\Core\AlternateUrl;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class AlternateUrlCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AlternateUrl::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AlternateUrl')
            ->setEntityLabelInPlural('AlternateUrl')
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
