<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\OAuthServerBundle\Form\AllowedGrantTypesChoiceType;
use Alchemy\OAuthServerBundle\Form\AllowedScopesChoiceType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OAuthClientCrudController extends AbstractAdminCrudController
{
    private array $allowedScopesChoices;

    /**
     * @param AllowedScopesChoiceType $allowedScopesChoiceType
     * bc with oauth bundle : AllowedScopesChoiceType gets argumant injection $scopes: '%alchemy_oauth_server.allowed_scopes%'
     *                        There is no getter, but we can get values via a resolver.
     */
    public function __construct(AllowedScopesChoiceType $allowedScopesChoiceType)
    {
        $resolver = new OptionsResolver();

        $allowedScopesChoiceType->configureOptions($resolver);
        $r = $resolver->resolve();
        $this->allowedScopesChoices = $r['choices'];
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

        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $randomId = TextField::new('randomId');
        $secret = TextField::new('secret')->setTemplatePath('@AlchemyAdmin/list/secret.html.twig');
        $allowedGrantTypes = ChoiceField::new('allowedGrantTypes')->setChoices($allowedGrantTypesChoices)->allowMultipleChoices();
        $allowedScopes = ChoiceField::new('allowedScopes')->setChoices($this->allowedScopesChoices)->allowMultipleChoices();
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
