<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\PublicationAsset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PublicationAssetCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PublicationAsset::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('PublicationAsset')
            ->setEntityLabelInPlural('PublicationAsset');
    }

    public function configureFields(string $pageName): iterable
    {
        $publication = AssociationField::new('publication');
        $asset = AssociationField::new('asset');
        $slug = TextField::new('slug');
        $position = IntegerField::new('position');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $publicationTitle = TextareaField::new('publication.title');
        $publicationId = TextareaField::new('publication.id', 'Publication ID');
        $assetTitle = TextareaField::new('asset.title');
        $assetId = IdField::new('asset.id', 'Asset ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $publicationTitle, $publicationId, $assetTitle, $assetId, $slug, $position, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $slug, $position, $createdAt, $clientAnnotations, $publication, $asset];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$publication, $asset, $slug, $position, $clientAnnotations];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$publication, $asset, $slug, $position, $clientAnnotations];
        }

        return [];
    }
}
