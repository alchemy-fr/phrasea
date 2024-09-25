<?php

namespace App\Controller\Admin;

use App\Entity\Core\AttributeEntity;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class AttributeEntityCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeEntity::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('type'))
            ->add(TextFilter::new('value'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute Entity')
            ->setEntityLabelInPlural('Attribute Entities')
            ->setSearchFields(['id', 'position', 'type', 'value'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace');
        yield TextField::new('type');
        yield TextField::new('value');
        yield JsonField::new('translations');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
