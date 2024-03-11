<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\FormSchema;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FormSchemaCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return FormSchema::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('FormSchema')
            ->setEntityLabelInPlural('FormSchema')
            ->setSearchFields(['id', 'locale', 'data']);
    }

    public function configureFields(string $pageName): iterable
    {
        $target = AssociationField::new('target');
        $locale = TextField::new('locale');
        $jsonData = TextareaField::new('jsonData');
        $id = IdField::new();
        $data = JsonField::new('data');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $locale, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $locale, $createdAt, $updatedAt, $target, $data];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$target, $locale, $jsonData];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$target, $locale, $jsonData];
        }

        return [];
    }
}
