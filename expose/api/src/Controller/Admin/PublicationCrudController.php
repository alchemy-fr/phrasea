<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Publication;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PublicationCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Publication::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Publication')
            ->setEntityLabelInPlural('Publication')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $parent = AssociationField::new('parent');
        $title = TextField::new('title')->setTemplatePath('admin/list/publication_title_link.html.twig');
        $description = TextareaField::new('description');
        $slug = TextField::new('slug');
        $profile = AssociationField::new('profile');
        $date = DateTimeField::new('date');
        $config = Field::new('config');
        $ownerId = TextField::new('ownerId');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $zippyId = TextField::new('zippyId');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $zippyHash = TextField::new('zippyHash');
        $configEnabled = Field::new('config.enabled');
        $configDownloadViaEmail = Field::new('config.downloadViaEmail');
        $configIncludeDownloadTermsInZippy = Field::new('config.includeDownloadTermsInZippy');
        $configUrls = TextField::new('config.urls');
        $configCopyrightText = TextareaField::new('config.copyrightText');
        $configCss = TextareaField::new('config.css');
        $configLayout = TextField::new('config.layout');
        $configTheme = TextField::new('config.theme');
        $configPubliclyListed = Field::new('config.publiclyListed');
        $configDownloadEnabled = Field::new('config.downloadEnabled');
        $configBeginsAt = DateTimeField::new('config.beginsAt');
        $configExpiresAt = DateTimeField::new('config.expiresAt');
        $configSecurityMethod = TextField::new('config.securityMethod');
        $configSecurityOptions = TextField::new('config.securityOptions');
        $configMapOptions = TextField::new('config.mapOptions');
        $configLayoutOptions = TextField::new('config.layoutOptions');
        $configTermsText = TextareaField::new('config.terms.text');
        $configTermsUrl = TextField::new('config.terms.url');
        $configDownloadTermsText = TextareaField::new('config.downloadTerms.text');
        $configDownloadTermsUrl = TextField::new('config.downloadTerms.url');
        $assets = AssociationField::new('assets');
        $package = AssociationField::new('package');
        $cover = AssociationField::new('cover');
        $children = AssociationField::new('children');
        $childrenCount = TextareaField::new('children.count');
        $assetsCount = TextareaField::new('assets.count');
        $publiclyListed = BooleanField::new('publiclyListed');
        $enabled = BooleanField::new('enabled');
        $securityMethod = TextareaField::new('securityMethod');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $slug, $profile, $parent, $childrenCount, $assetsCount, $publiclyListed, $enabled, $securityMethod, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $description, $ownerId, $slug, $date, $createdAt, $zippyId, $zippyHash, $clientAnnotations, $configEnabled, $configDownloadViaEmail, $configIncludeDownloadTermsInZippy, $configUrls, $configCopyrightText, $configCss, $configLayout, $configTheme, $configPubliclyListed, $configDownloadEnabled, $configBeginsAt, $configExpiresAt, $configSecurityMethod, $configSecurityOptions, $configMapOptions, $configLayoutOptions, $configTermsText, $configTermsUrl, $configDownloadTermsText, $configDownloadTermsUrl, $assets, $profile, $package, $cover, $parent, $children];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        }
    }
}
