<?php

namespace App\Controller\Admin;

use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OAuthClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OAuthClient::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('OAuthClient')
            ->setEntityLabelInPlural('OAuthClient')
            ->setSearchFields(['randomId', 'redirectUris', 'secret', 'allowedGrantTypes', 'id', 'allowedScopes'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $id = TextField::new('id', 'ID');
        $randomId = TextField::new('randomId');
        $secret = TextField::new('secret')->setTemplatePath('@AlchemyAdmin/list/secret.html.twig');
        $allowedGrantTypes = ArrayField::new('allowedGrantTypes');
        $allowedScopes = ArrayField::new('allowedScopes');
        $redirectUris = ArrayField::new('redirectUris');
        $createdAt = DateTimeField::new('createdAt');
        $publicId = TextareaField::new('publicId', 'Client ID')->setTemplatePath('@AlchemyAdmin/list/code.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$publicId, $secret, $allowedScopes, $allowedGrantTypes, $redirectUris];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$randomId, $redirectUris, $secret, $allowedGrantTypes, $id, $createdAt, $allowedScopes];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$id, $randomId, $secret, $allowedGrantTypes, $allowedScopes, $redirectUris];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $randomId, $secret, $allowedGrantTypes, $allowedScopes, $redirectUris];
        }
    }
}
