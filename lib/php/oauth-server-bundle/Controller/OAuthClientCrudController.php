<?php

namespace Alchemy\OAuthServerBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\OAuthServerBundle\Field\AllowedScopesChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OAuthClientCrudController extends AbstractAdminCrudController
{
    private AllowedScopesChoiceField $allowedScopesChoiceField;

    public function __construct(AllowedScopesChoiceField $allowedScopesChoiceField)
    {
        $this->allowedScopesChoiceField = $allowedScopesChoiceField;
    }

    public static function getEntityFqcn(): string
    {
        return OAuthClient::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('OAuthClient')
            ->setEntityLabelInPlural('OAuthClient')
            ->setSearchFields(['randomId', 'redirectUris', 'secret', 'allowedGrantTypes', 'id', 'allowedScopes']);
    }

    public function configureFields(string $pageName): iterable
    {
        $allowedGrantTypesChoices = [];
        foreach ([
                     'authorization_code',
                     'password',
                     'client_credentials',
                     'refresh_token',
                 ] as $scope) {
            $allowedGrantTypesChoices[$scope] = $scope;
        }

        $id = IdField::new();
        $randomId = TextField::new('randomId');
        $secret = TextField::new('secret')->setTemplatePath('@AlchemyAdmin/list/secret.html.twig');
        $allowedGrantTypes = ChoiceField::new('allowedGrantTypes')
            ->setChoices($allowedGrantTypesChoices)
            ->allowMultipleChoices()
            ->renderExpanded(true);
        $allowedScopes = $this->allowedScopesChoiceField->create('allowedScopes');
        $redirectUris = ArrayField::new('redirectUris');
        $createdAt = DateTimeField::new('createdAt');
        $publicId = TextareaField::new('publicId', 'Client ID')->setTemplatePath('@AlchemyAdmin/list/code.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$publicId, $secret, $allowedScopes, $allowedGrantTypes, $redirectUris];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$randomId, $redirectUris, $secret, $allowedGrantTypes, $id, $createdAt, $allowedScopes];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$id, $randomId, $secret, $allowedGrantTypes, $allowedScopes, $redirectUris];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$id, $randomId, $secret, $allowedGrantTypes, $allowedScopes, $redirectUris];
        }

        return [];
    }
}
