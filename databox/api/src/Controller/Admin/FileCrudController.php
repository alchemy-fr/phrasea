<?php

namespace App\Controller\Admin;

use App\Entity\Core\File;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;

class FileCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return File::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('File')
            ->setEntityLabelInPlural('File')
            ->setSearchFields(['id', 'type', 'size', 'checksum', 'path', 'storage', 'originalName', 'extension', 'alternateUrls', 'metadata'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('storage'))
            ->add(BooleanFilter::new('pathPublic'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('path');
        yield TextField::new('storage');
        yield TextField::new('originalName')
            ->hideOnIndex();
        yield AssociationField::new('workspace');
        yield TextField::new('type')
            ->hideOnIndex();
        yield IntegerField::new('size')
            ->hideOnIndex();
        yield TextField::new('checksum')
            ->hideOnIndex();
        yield Field::new('pathPublic');
        yield TextField::new('extension')
            ->hideOnIndex();
        yield ArrayField::new('alternateUrls')
            ->hideOnIndex();
        yield TextField::new('metadata')
            ->onlyOnDetail();        
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();

    }
}
