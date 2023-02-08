<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\PublicationProfile;
use App\Field\LayoutOptionsField;
use App\Field\MapOptionsField;
use App\Field\PublicationConfigField;
use App\Field\SecurityMethodChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class PublicationProfileCrudController extends AbstractAdminCrudController
{
    use PermissionTrait;

    public static function getEntityFqcn(): string
    {
        return PublicationProfile::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'profile',
                ]
            )
            ->createAsGlobalAction()
        ;

        $permissionsAction = Action::new('permissions')
            ->linkToCrudAction('permissions')
        ;

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $globalPermissionsAction)
            ->add(Crud::PAGE_INDEX, $permissionsAction);

    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud);
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $config = PublicationConfigField::new('config');
        $ownerId = IdField::new('ownerId');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $configEnabled = Field::new('config.enabled', 'Enabled');
        $configDownloadViaEmail = Field::new('config.downloadViaEmail');
        $configIncludeDownloadTermsInZippy = Field::new('config.includeDownloadTermsInZippy');
        $configUrls = ArrayField::new('config.urls');
        $configCopyrightText = TextareaField::new('config.copyrightText');
        $configCss = TextareaField::new('config.css');
        $configLayout = TextField::new('config.layout', 'Layout');
        $configTheme = TextField::new('config.theme', 'Theme');
        $configPubliclyListed = Field::new('config.publiclyListed', 'PubliclyListed');
        $configDownloadEnabled = Field::new('config.downloadEnabled', 'DownloadEnabled');
        $configBeginsAt = DateTimeField::new('config.beginsAt');
        $configExpiresAt = DateTimeField::new('config.expiresAt');
        $configSecurityMethod = TextField::new('config.securityMethod', 'SecurityMethod');
        $configSecurityOptions = SecurityMethodChoiceField::new('config.securityOptions', 'SecurityOptions');
        $configMapOptions = MapOptionsField::new('config.mapOptions');
        $configLayoutOptions = LayoutOptionsField::new('config.layoutOptions');
        $configTermsText = TextareaField::new('config.terms.text');
        $configTermsUrl = TextField::new('config.terms.url');
        $configDownloadTermsText = TextareaField::new('config.downloadTerms.text');
        $configDownloadTermsUrl = TextField::new('config.downloadTerms.url');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $configLayout, $configEnabled, $configTheme, $configPubliclyListed, $configSecurityMethod, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            // todo EA3 : resore map & layout
            return [$id, $name, $ownerId, $createdAt, $clientAnnotations, $configEnabled, $configDownloadViaEmail, $configIncludeDownloadTermsInZippy, $configUrls, $configCopyrightText, $configCss, $configLayout, $configTheme, $configPubliclyListed, $configDownloadEnabled, $configBeginsAt, $configExpiresAt, $configSecurityMethod, $configSecurityOptions /*, $configMapOptions, $configLayoutOptions */, $configTermsText, $configTermsUrl, $configDownloadTermsText, $configDownloadTermsUrl];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $config, $ownerId, $clientAnnotations];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $config, $ownerId, $clientAnnotations];
        }

        return [];
    }

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var PublicationProfile $publicationProfile */
        $publicationProfile = $adminContext->getEntity()->getInstance();
        $id = $publicationProfile->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->permissionView->getObjectKey(PublicationProfile::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig', $twigParameters);
    }
}
