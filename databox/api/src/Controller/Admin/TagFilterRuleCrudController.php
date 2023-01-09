<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Core\TagFilterRule;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TagFilterRuleCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return TagFilterRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TagFilterRule')
            ->setEntityLabelInPlural('TagFilterRule')
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = IntegerField::new('userType');
        $userId = TextField::new('userId');
        $objectType = IntegerField::new('objectType');
        $objectId = TextField::new('objectId');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $include = AssociationField::new('include');
        $exclude = AssociationField::new('exclude');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $include, $exclude, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $createdAt, $updatedAt, $include, $exclude];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $createdAt, $updatedAt, $include, $exclude];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $createdAt, $updatedAt, $include, $exclude];
        }

        return [];
    }
}
