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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
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
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('title');
        yield AssociationField::new('publication');
        yield IdField::new('ownerId');
        yield TextField::new('originalName')
            ->hideOnIndex();
        yield TextareaField::new('description')
            ->hideOnIndex();
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield NumberField::new('lat');
        yield NumberField::new('lng');
        yield NumberField::new('altitude')
            ->hideOnIndex();
        yield JsonField::new('webVTT')
            ->hideOnIndex();
        yield JsonField::new('clientAnnotations')
            ->hideOnIndex();
        yield IdField::new('assetId')
            ->hideOnIndex();
        yield TextField::new('path')
            ->hideOnIndex();
        yield IntegerField::new('size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        yield TextField::new('mimeType');
        yield DateTimeField::new('createdAt')
            ->hideOnForm()
        ;
        yield AssociationField::new('subDefinitions')
            ->hideOnIndex()
            ->hideOnForm()
        ;
        yield TextareaField::new('geoPoint')
            ->hideOnForm()
            ->hideOnIndex();
        yield NumberField::new('position');
    }
}
