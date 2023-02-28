<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class PublicationProfileCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PublicationProfile::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $config = PublicationConfigField::new('config');
        $ownerId = IdField::new('ownerId');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $id = IdField::new();
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
        $configTermsText = TextareaField::new('config.terms.text');
        $configTermsUrl = TextField::new('config.terms.url');
        $configDownloadTermsText = TextareaField::new('config.downloadTerms.text');
        $configDownloadTermsUrl = TextField::new('config.downloadTerms.url');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $configLayout, $configEnabled, $configTheme, $configPubliclyListed, $configSecurityMethod, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $ownerId, $createdAt, $clientAnnotations, $configEnabled, $configDownloadViaEmail, $configIncludeDownloadTermsInZippy, $configUrls, $configCopyrightText, $configCss, $configLayout, $configTheme, $configPubliclyListed, $configDownloadEnabled, $configBeginsAt, $configExpiresAt, $configSecurityMethod, $configSecurityOptions, $configTermsText, $configTermsUrl, $configDownloadTermsText, $configDownloadTermsUrl];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $config, $ownerId, $clientAnnotations];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $config, $ownerId, $clientAnnotations];
        }

        return [];
    }
}
