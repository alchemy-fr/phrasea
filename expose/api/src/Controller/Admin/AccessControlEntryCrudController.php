<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccessControlEntryCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccessControlEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = IntegerField::new('userType');
        $userId = TextField::new('userId', 'ID');
        $objectType = TextField::new('objectType');
        $objectId = TextField::new('objectId');
        $permissions = Field::new('permissions');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $mask = IntegerField::new('mask');
        $createdAt = DateTimeField::new('createdAt');
        $userTypeString = TextareaField::new('userTypeString');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$userTypeString, $userId, $objectType, $objectId, $mask];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $mask, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $permissions];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $permissions];
        }
        return [];
    }
}
