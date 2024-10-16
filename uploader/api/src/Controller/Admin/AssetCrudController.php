<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
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
            ->setPaginatorPageSize(100)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'path', 'size', 'originalName', 'mimeType', 'userId']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield IdField::new('userId');
        yield IntegerField::new('size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig')
            ->hideOnIndex();
        yield TextField::new('originalName')
            ->hideOnIndex();
        yield TextField::new('mimeType')
            ->hideOnIndex();
        yield TextField::new('path')
            ->hideOnIndex();
        yield JsonField::new('formData')
            ->hideOnIndex();
        yield JsonField::new('data')
            ->hideOnIndex();
        yield BooleanField::new('acknowledged')->renderAsSwitch(false);
        yield AssociationField::new('target');
        yield AssociationField::new('commit')
        ;
        yield BooleanField::new('committed')->renderAsSwitch(false);
        yield DateTimeField::new('createdAt');
    }
}
