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
        $id = IdField::new();
        $originalName = TextField::new('originalName');
        $description = TextareaField::new('description');
        $lat = NumberField::new('lat');
        $lng = NumberField::new('lng');
        $altitude = NumberField::new('altitude');
        $webVTT = JsonField::new('webVTT');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $assetId = IdField::new('assetId');
        $path = TextField::new('path');
        $size = IntegerField::new('size')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $title = TextField::new('title');
        $mimeType = TextField::new('mimeType');
        $ownerId = IdField::new('ownerId');
        $createdAt = DateTimeField::new('createdAt');
        $publication = AssociationField::new('publication');
        $subDefinitions = AssociationField::new('subDefinitions');
        $geoPoint = TextareaField::new('geoPoint');
        $position = NumberField::new('position');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $publication, $title, $originalName, $size, $geoPoint, $path, $position, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $assetId, $title, $path, $size, $publication, $description, $originalName, $mimeType, $ownerId, $lat, $lng, $webVTT, $altitude, $createdAt, $clientAnnotations, $subDefinitions];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$publication, $title, $originalName, $description, $lat, $lng, $altitude, $webVTT, $clientAnnotations];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$publication, $title, $originalName, $description, $lat, $lng, $altitude, $webVTT, $clientAnnotations];
        }

        return [];
    }
}
