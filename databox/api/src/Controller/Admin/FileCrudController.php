<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Core\File;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

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
            ->setEntityLabelInPlural('Files')
            ->setSearchFields(['id', 'type', 'size', 'checksum', 'path', 'storage', 'originalName', 'extension', 'alternateUrls', 'metadata']);
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
        yield BooleanField::new('pathPublic')
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
        yield JsonField::new('metadata')
            ->onlyOnDetail();
        yield JsonField::new('analysis')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();

    }
}
