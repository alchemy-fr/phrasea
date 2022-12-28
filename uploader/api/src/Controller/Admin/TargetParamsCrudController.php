<?php

namespace App\Controller\Admin;

use App\Entity\TargetParams;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TargetParamsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TargetParams::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('TargetParams')
            ->setEntityLabelInPlural('TargetParams')
            ->setSearchFields(['id', 'data'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $jsonData = Field::new('jsonData');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $data = TextField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $data, $createdAt, $updatedAt, $target];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $jsonData];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $jsonData];
        }
        return [];
    }
}
