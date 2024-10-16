<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
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
        yield IdField::new();
        yield TextField::new('type');
        yield IdField::new('uploadId');
        yield TextField::new('filename');
        yield TextField::new('sizeAsString');
        yield TextField::new('path');
        yield BooleanField::new('complete')->renderAsSwitch(false);
        yield DateTimeField::new('createdAt');
        yield IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');

        return [];
    }
}