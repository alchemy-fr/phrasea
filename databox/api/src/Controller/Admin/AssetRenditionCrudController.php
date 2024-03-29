<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\AssetRendition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AssetRenditionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetRendition::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AssetRendition')
            ->setEntityLabelInPlural('AssetRendition')
            ->setSearchFields(['id'])
            ->setPaginatorPageSize(100);
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
        $id = IdField::new();
        $fileId = IdField::new('file.id');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $definition, $asset, $fileId, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $ready, $createdAt, $updatedAt, $definition, $asset, $file];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$ready, $createdAt, $updatedAt, $definition, $asset, $file];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$ready, $createdAt, $updatedAt, $definition, $asset, $file];
        }

        return [];
    }
}
