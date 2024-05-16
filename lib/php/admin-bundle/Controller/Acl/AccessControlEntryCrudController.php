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
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();

        yield $this->userTypeChoiceField->create('userType');
        yield TextField::new('userId', 'ID');
        yield $this->objectTypeChoiceField->create('objectType');
        yield TextField::new('objectId');
        yield $this->permissionsChoiceField->create('permissions')
            ->onlyOnForms();
        yield IntegerField::new('mask')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
