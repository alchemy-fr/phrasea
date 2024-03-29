<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller\Acl;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\Acl\ObjectTypeChoiceField;
use Alchemy\AdminBundle\Field\Acl\PermissionsChoiceField;
use Alchemy\AdminBundle\Field\Acl\UserTypeChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccessControlEntryCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccessControlEntry::class;
    }

    public function __construct(private readonly PermissionsChoiceField $permissionsChoiceField, private readonly UserTypeChoiceField $userTypeChoiceField, private readonly ObjectTypeChoiceField $objectTypeChoiceField)
    {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId', 'mask']);
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = $this->userTypeChoiceField->create('userType');
        $userId = TextField::new('userId', 'ID');
        $objectType = $this->objectTypeChoiceField->create('objectType');
        $objectId = TextField::new('objectId');
        $permissions = $this->permissionsChoiceField->create('permissions');
        $id = IdField::new();
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
