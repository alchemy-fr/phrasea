<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
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
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId', 'mask'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = IntegerField::new('userType');
        $userId = IdField::new('userId', 'User ID');
        $objectType = TextField::new('objectType');
        $objectId = IdField::new('objectId');
        $permissions = ArrayField::new('permissions');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $mask = IntegerField::new('mask');
        $createdAt = DateTimeField::new('createdAt');
        $userTypeString = TextareaField::new('userTypeString');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$userTypeString, $userId, $objectType, $objectId, $mask];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $mask, $createdAt, $permissions];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $mask, $permissions];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $permissions];
        }
        return [];
    }
}