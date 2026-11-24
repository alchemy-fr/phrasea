<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller\Acl;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\Acl\ObjectTypeChoiceField;
use Alchemy\AdminBundle\Field\Acl\PermissionsChoiceField;
use Alchemy\AdminBundle\Field\Acl\UserTypeChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('userType')->setChoices(AccessControlEntry::USER_TYPES))
            ->add(AssociationIdentifierFilter::new('userId', 'User/Group ID'))
            ->add(ChoiceFilter::new('objectType')->setChoices($this->objectTypeChoiceField->getChoices()))
            ->add(AssociationIdentifierFilter::new('objectId'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();

        yield $this->userTypeChoiceField->create('userType');
        yield TextField::new('userId', 'User/Group ID');
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
