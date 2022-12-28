<?php

namespace App\Controller\Admin;

use App\Entity\Core\File;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class FileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return File::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('File')
            ->setEntityLabelInPlural('File')
            ->setSearchFields(['id', 'type', 'size', 'checksum', 'path', 'storage', 'originalName', 'extension', 'alternateUrls', 'metadata'])
            ->setPaginatorPageSize(200)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
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
        $size = TextField::new('size');
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
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
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
