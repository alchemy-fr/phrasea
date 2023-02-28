<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\File;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->setPaginatorPageSize(200);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('storage');
    }

    public function configureFields(string $pageName): iterable
    {
        $type = TextField::new('type');
        $size = IntegerField::new('size');
        $checksum = TextField::new('checksum');
        $path = TextField::new('path');
        $pathPublic = Field::new('pathPublic');
        $storage = TextField::new('storage');
        $originalName = TextField::new('originalName');
        $extension = TextField::new('extension');
        $alternateUrls = ArrayField::new('alternateUrls');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $workspace = AssociationField::new('workspace');
        $id = IdField::new();
        $metadata = TextField::new('metadata');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $path, $storage, $workspace, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $type, $size, $checksum, $path, $pathPublic, $storage, $originalName, $extension, $alternateUrls, $metadata, $createdAt, $updatedAt, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$type, $size, $checksum, $path, $pathPublic, $storage, $originalName, $extension, $alternateUrls, $createdAt, $updatedAt, $workspace];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$type, $size, $checksum, $path, $pathPublic, $storage, $originalName, $extension, $alternateUrls, $createdAt, $updatedAt, $workspace];
        }

        return [];
    }
}
