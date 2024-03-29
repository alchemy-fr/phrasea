<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\AssetTitleAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AssetTitleAttributeCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetTitleAttribute::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AssetTitleAttribute')
            ->setEntityLabelInPlural('AssetTitleAttribute')
            ->setSearchFields(['id', 'priority'])
            ->setPaginatorPageSize(200);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(EntityFilter::new('definition'));
    }

    public function configureFields(string $pageName): iterable
    {
        $workspace = AssociationField::new('workspace');
        $definition = AssociationField::new('definition');
        $priority = IntegerField::new('priority');
        $overrides = BooleanField::new('overrides');
        $id = IdField::new();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $definition, $priority, $overrides];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $priority, $overrides, $workspace, $definition];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $definition, $priority, $overrides];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $definition, $priority, $overrides];
        }

        return [];
    }
}
