<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Group;
use App\Form\RoleChoiceHelper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GroupCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Group')
            ->setEntityLabelInPlural('Group')
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $roles = ChoiceField::new('roles')
            ->setChoices(RoleChoiceHelper::getRoleChoices($this->authorizationChecker))
            ->allowMultipleChoices()
            ->renderExpanded()
        ;
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');
        $users = AssociationField::new('users');
        $userCount = IntegerField::new('userCount');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $roles, $userCount, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $roles, $createdAt, $users];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $roles];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $roles];
        }

        return [];
    }
}
