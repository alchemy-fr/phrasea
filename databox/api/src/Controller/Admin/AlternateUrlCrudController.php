<?php

namespace App\Controller\Admin;

use App\Entity\Core\AlternateUrl;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AlternateUrlCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AlternateUrl::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AlternateUrl')
            ->setEntityLabelInPlural('AlternateUrl')
            ->setSearchFields(['id', 'type', 'label'])
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
        $workspace = AssociationField::new('workspace');
        $type = TextField::new('type');
        $label = TextField::new('label');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $type, $label];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $type, $label, $createdAt, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $type, $label];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $type, $label];
        }
    }
}
