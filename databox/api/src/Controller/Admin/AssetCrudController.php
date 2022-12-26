<?php

namespace App\Controller\Admin;

use App\Entity\Core\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy'])
            ->setPaginatorPageSize(200)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'));
    }

    public function configureFields(string $pageName): iterable
    {
        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $startingCollections = Field::new('startingCollections');
        $tags = AssociationField::new('tags');
        $privacy = IntegerField::new('privacy')->setTemplatePath('admin/field_privacy.html.twig');
        $ownerId = TextField::new('ownerId');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $locale = TextField::new('locale');
        $collections = AssociationField::new('collections');
        $storyCollection = AssociationField::new('storyCollection');
        $referenceCollection = AssociationField::new('referenceCollection');
        $attributes = AssociationField::new('attributes');
        $file = AssociationField::new('file');
        $renditions = AssociationField::new('renditions');
        // todo: EA3
//        $collectionsCount = TextareaField::new('collections.count', '# Colls');

        if (Crud::PAGE_INDEX === $pageName) {
            // return [$id, $title, $workspace, $privacy, $collectionsCount, $file, $key, $createdAt];
            return [$id, $title, $workspace, $privacy, $file, $key, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerId, $key, $createdAt, $updatedAt, $locale, $privacy, $collections, $tags, $storyCollection, $referenceCollection, $attributes, $file, $renditions, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $startingCollections, $tags, $privacy, $ownerId];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $tags, $startingCollections, $privacy, $ownerId];
        }
    }
}
