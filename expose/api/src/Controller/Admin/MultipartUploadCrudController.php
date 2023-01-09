<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MultipartUploadCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return MultipartUpload::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('MultipartUpload')
            ->setEntityLabelInPlural('MultipartUpload');
    }

    public function configureFields(string $pageName): iterable
    {
        $filename = TextField::new('filename');
        $type = TextField::new('type');
        $sizeAsString = TextField::new('sizeAsString');
        $uploadId = IdField::new('uploadId');
        $path = TextField::new('path');
        $complete = BooleanField::new('complete');
        $createdAt = DateTimeField::new('createdAt');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $filename, $type, $size, $path, $uploadId, $complete, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$filename, $type, $sizeAsString, $uploadId, $path, $complete, $createdAt];
        }

        return [];
    }
}
