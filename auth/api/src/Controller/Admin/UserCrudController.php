<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('User')
            ->setSearchFields(['username'])
            ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        $username = TextField::new('username');
        $userRoles = ArrayField::new('userRoles');
        $enabled = Field::new('enabled');
        $groups = AssociationField::new('groups');
        $inviteByEmail = Field::new('inviteByEmail');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $emailVerified = Field::new('emailVerified');
        $securityToken = TextField::new('securityToken');
        $salt = TextField::new('salt');
        $roles = TextField::new('roles');
        $password = TextField::new('password');
        $locale = TextField::new('locale');
        $createdAt = DateTimeField::new('createdAt');
        $lastInviteAt = DateTimeField::new('lastInviteAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $username, $enabled, $groups, $userRoles, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $username, $emailVerified, $enabled, $securityToken, $salt, $roles, $password, $locale, $createdAt, $lastInviteAt, $updatedAt, $groups];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$username, $userRoles, $enabled, $groups, $inviteByEmail];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$username, $userRoles, $enabled, $groups];
        }
        return [];
    }
}
