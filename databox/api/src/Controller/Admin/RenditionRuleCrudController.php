<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Core\RenditionRule;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RenditionRuleCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('RenditionRule')
            ->setEntityLabelInPlural('RenditionRule')
            ->setSearchFields(['id', 'userType', 'userId', 'objectType', 'objectId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        $userType = IntegerField::new('userType');
        $userId = TextField::new('userId');
        $objectType = IntegerField::new('objectType');
        $objectId = TextField::new('objectId');
        $allowed = AssociationField::new('allowed');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $allowed, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userType, $userId, $objectType, $objectId, $createdAt, $updatedAt, $allowed];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $allowed];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$userType, $userId, $objectType, $objectId, $allowed];
        }

        return [];
    }
}
