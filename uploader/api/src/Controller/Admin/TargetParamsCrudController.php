<?php

namespace App\Controller\Admin;

use App\Entity\TargetParams;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;

class TargetParamsCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return TargetParams::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TargetParams')
            ->setEntityLabelInPlural('TargetParams')
            ->setSearchFields(['id', 'data']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('target'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('target');
        yield TextareaField::new('jsonData')
            ->onlyOnForms();
        yield JsonField::new('data')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();    
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
    }
}
