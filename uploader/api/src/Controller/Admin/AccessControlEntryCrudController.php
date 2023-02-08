<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Field\ObjectTypeChoiceField;
use Alchemy\AclBundle\Field\PermissionsChoiceField;
use Alchemy\AclBundle\Field\UserTypeChoiceField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class AccessControlEntryCrudController extends AbstractAdminCrudController
{
    private PermissionsChoiceField $permissionsChoiceField;
    private UserTypeChoiceField $userTypeChoiceField;
    private ObjectTypeChoiceField $objectTypeChoiceField;


    public static function getEntityFqcn(): string
    {
        return AccessControlEntry::class;
    }

    public function __construct(PermissionsChoiceField $permissionsChoiceField, UserTypeChoiceField $userTypeChoiceField, ObjectTypeChoiceField $objectTypeChoiceField)
    {
        $this->permissionsChoiceField = $permissionsChoiceField;
        $this->userTypeChoiceField = $userTypeChoiceField;
        $this->objectTypeChoiceField = $objectTypeChoiceField;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId', 'mask']);
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = $this->userTypeChoiceField->create('userType');
        $userId = IdField::new('userId', 'User ID');
        $objectType = $this->objectTypeChoiceField->create('objectType');
        $objectId = IdField::new('objectId');
        $permissions = $this->permissionsChoiceField->create('permissions');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $mask = IntegerField::new('mask');
        $createdAt = DateTimeField::new('createdAt');
        $userTypeString = TextareaField::new('userTypeString');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$userTypeString, $userId, $objectType, $objectId, $mask];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $mask, $createdAt, $permissions];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $permissions];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $permissions];
        }

        return [];
    }
}
