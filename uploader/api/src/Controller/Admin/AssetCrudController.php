<?php

namespace App\Controller\Admin;

use App\Entity\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AssetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Asset::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ->setSearchFields(['id', 'path', 'size', 'originalName', 'mimeType', 'userId'])
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
        $path = TextField::new('path');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $originalName = TextField::new('originalName');
        $mimeType = TextField::new('mimeType');
        $acknowledged = BooleanField::new('acknowledged');
        $createdAt = DateTimeField::new('createdAt');
        $userId = TextField::new('userId')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $target = AssociationField::new('target');
        $commit = AssociationField::new('commit');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $committed = BooleanField::new('committed');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $originalName, $size, $userId, $committed, $acknowledged, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $path, $size, $originalName, $mimeType, $acknowledged, $createdAt, $userId, $target, $commit];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$path, $size, $originalName, $mimeType, $acknowledged, $createdAt, $userId, $target, $commit];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$path, $size, $originalName, $mimeType, $acknowledged, $createdAt, $userId, $target, $commit];
        }
        return [];
    }
}
