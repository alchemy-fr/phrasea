<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AssetCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Asset::class;
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
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ->setSearchFields(['id', 'path', 'size', 'originalName', 'mimeType', 'userId']);
    }

    public function configureFields(string $pageName): iterable
    {
        $path = TextField::new('path');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $originalName = TextField::new('originalName');
        $mimeType = TextField::new('mimeType');
        $acknowledged = BooleanField::new('acknowledged')->renderAsSwitch(false);
        $createdAt = DateTimeField::new('createdAt');
        $userId = IdField::new('userId');
        $target = AssociationField::new('target');
        $commit = AssociationField::new('commit');
        $id = IdField::new();
        $committed = BooleanField::new('committed')->renderAsSwitch(false);

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
