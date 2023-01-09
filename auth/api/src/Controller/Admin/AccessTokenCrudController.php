<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccessTokenCrudController extends AbstractAdminCrudController  // AbstractAdminController
{
    public static function getEntityFqcn(): string
    {
        return AccessToken::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AccessToken')
            ->setEntityLabelInPlural('AccessToken')
            ->setSearchFields(['token']);
    }

    public function configureFields(string $pageName): iterable
    {
        $token = TextField::new('token');
        $expiresAt = IntegerField::new('expiresAt');
        $scope = TextField::new('scope');
        $createdAt = DateTimeField::new('createdAt');
        $client = AssociationField::new('client');
        $user = AssociationField::new('user');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $user, $token, $scope, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$token, $expiresAt, $scope, $id, $createdAt, $client, $user];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$token, $expiresAt, $scope, $createdAt, $client, $user];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$token, $expiresAt, $scope, $createdAt, $client, $user];
        }

        return [];
    }
}
