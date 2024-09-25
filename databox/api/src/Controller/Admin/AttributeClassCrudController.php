<?php

namespace App\Controller\Admin;

use App\Entity\Core\AttributeClass;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\JsonField;

class AttributeClassCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeClass::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AttributeClass')
            ->setEntityLabelInPlural('AttributeClass')
            ->setSearchFields(['id', 'name', 'key'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('public'))
            ->add(BooleanFilter::new('editable'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('workspace');   
        yield TextField::new('name');
        yield BooleanField::new('public');
        yield BooleanField::new('editable');
        yield TextField::new('key')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();    
        yield AssociationField::new('definitions')
            ->onlyOnDetail();    
        yield JsonField::new('labels')
            ->hideOnIndex();    
    }
}
