<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Core\AssetTitleAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
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
            ->setPaginatorPageSize(200)
            ;
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
        $overrides = Field::new('overrides');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');

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
