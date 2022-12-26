<?php

namespace App\Controller\Admin;

use App\Entity\Core\AssetRendition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AssetRenditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetRendition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AssetRendition')
            ->setEntityLabelInPlural('AssetRendition')
            ->setSearchFields(['id'])
            ->setPaginatorPageSize(100)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('definition'))
            ->add(EntityFilter::new('asset'));
    }

    public function configureFields(string $pageName): iterable
    {
        $ready = Field::new('ready');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $definition = AssociationField::new('definition');
        $asset = AssociationField::new('asset');
        $file = AssociationField::new('file');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $fileId = TextareaField::new('file.id')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $definition, $asset, $fileId, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $ready, $createdAt, $updatedAt, $definition, $asset, $file];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$ready, $createdAt, $updatedAt, $definition, $asset, $file];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$ready, $createdAt, $updatedAt, $definition, $asset, $file];
        }
    }
}
