<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\RenditionClass;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class RenditionClassCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionClass::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('RenditionClass')
            ->setEntityLabelInPlural('RenditionClass')
            ->setSearchFields(['id', 'name'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('name');
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();
        $workspace = AssociationField::new('workspace');
        $name = TextField::new('name');
        $public = Field::new('public');
        $createdAt = DateTimeField::new('createdAt');
        $definitions = AssociationField::new('definitions');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $name, $public, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $public, $createdAt, $workspace, $definitions];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $name, $public];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $name, $public];
        }

        return [];
    }
}
