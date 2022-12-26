<?php

namespace App\Controller\Admin;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccessTokenCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccessToken::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AccessToken')
            ->setEntityLabelInPlural('AccessToken')
            ->setSearchFields(['token'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $token = TextField::new('token');
        $expiresAt = IntegerField::new('expiresAt');
        $scope = TextField::new('scope');
        $createdAt = DateTimeField::new('createdAt');
        $client = AssociationField::new('client');
        $id = Field::new('id', 'ID');
        $user = TextareaField::new('user');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$user, $token, $scope, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$token, $expiresAt, $scope, $id, $createdAt, $client];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$token, $expiresAt, $scope, $createdAt, $client];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$token, $expiresAt, $scope, $createdAt, $client];
        }
    }
}
