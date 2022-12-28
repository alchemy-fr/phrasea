<?php

namespace App\Controller\Admin;

use App\Entity\FormSchema;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FormSchemaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FormSchema::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('FormSchema')
            ->setEntityLabelInPlural('FormSchema')
            ->setSearchFields(['id', 'locale', 'data'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $locale = TextField::new('locale');
        $jsonData = Field::new('jsonData');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $data = TextField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $locale, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $locale, $data, $createdAt, $updatedAt, $target];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $locale, $jsonData];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $locale, $jsonData];
        }
        return [];
    }
}
