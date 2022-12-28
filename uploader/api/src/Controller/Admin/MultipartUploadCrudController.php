<?php

namespace App\Controller\Admin;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MultipartUploadCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MultipartUpload::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('MultipartUpload')
            ->setEntityLabelInPlural('MultipartUpload')
            ->setSearchFields(['id', 'filename', 'type', 'sizeAsString', 'uploadId', 'path'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable('new', 'edit');
    }

    public function configureFields(string $pageName): iterable
    {
        $filename = TextField::new('filename');
        $type = TextField::new('type');
        $sizeAsString = TextField::new('sizeAsString');
        $uploadId = TextField::new('uploadId');
        $path = TextField::new('path');
        $complete = BooleanField::new('complete');
        $createdAt = DateTimeField::new('createdAt');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $filename, $type, $size, $path, $uploadId, $complete, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        }
        return [];
    }
}
