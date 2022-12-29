<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
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
            ->remove(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $originalName = TextField::new('originalName');
        $description = TextareaField::new('description');
        $lat = NumberField::new('lat');
        $lng = NumberField::new('lng');
        $altitude = NumberField::new('altitude');
        $webVTT = TextareaField::new('webVTT');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $assetId = TextField::new('assetId');
        $path = TextField::new('path');
        $size = TextField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $title = TextField::new('title');
        $mimeType = TextField::new('mimeType');
        $ownerId = TextField::new('ownerId');
        $createdAt = DateTimeField::new('createdAt');
        $publications = AssociationField::new('publications');
        $subDefinitions = AssociationField::new('subDefinitions');
        $geoPoint = TextareaField::new('geoPoint');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $originalName, $size, $geoPoint, $path, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $assetId, $path, $size, $title, $description, $originalName, $mimeType, $ownerId, $lat, $lng, $webVTT, $altitude, $createdAt, $clientAnnotations, $publications, $subDefinitions];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$originalName, $description, $lat, $lng, $altitude, $webVTT, $clientAnnotations];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$originalName, $description, $lat, $lng, $altitude, $webVTT, $clientAnnotations];
        }
    }
}
