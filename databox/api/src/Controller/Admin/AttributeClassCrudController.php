<?php

namespace App\Controller\Admin;

use App\Entity\Core\AttributeClass;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AttributeClassCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeClass::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AttributeClass')
            ->setEntityLabelInPlural('AttributeClass')
            ->setSearchFields(['id', 'name', 'key'])
            ->setPaginatorPageSize(100)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('name')
            ->add('public')
            ->add('editable');
    }

    public function configureFields(string $pageName): iterable
    {
        $workspace = AssociationField::new('workspace');
        $name = TextField::new('name');
        $public = Field::new('public');
        $editable = Field::new('editable');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $definitions = AssociationField::new('definitions');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $name, $public, $editable, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $editable, $public, $key, $createdAt, $workspace, $definitions];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $name, $public, $editable];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $name, $public, $editable];
        }
    }
}
