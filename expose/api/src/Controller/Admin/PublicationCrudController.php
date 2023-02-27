<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Publication;
use App\Field\LayoutOptionsField;
use App\Field\MapOptionsField;
use App\Field\PublicationConfigField;
use App\Field\SecurityMethodChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class PublicationCrudController extends AbstractAdminCrudController
{
    use PermissionTrait;

    public static function getEntityFqcn(): string
    {
        return Publication::class;
    }

    public function __construct(PermissionView $permissionView)
    {
        $this->setPermissionView($permissionView);
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalPermissionsAction = Action::new('globalPermissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'publication',
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
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Publication')
            ->setEntityLabelInPlural('Publication');
    }

    public function configureFields(string $pageName): iterable
    {
        $parent = AssociationField::new('parent');
        $title = TextField::new('title')->setTemplatePath('admin/list/publication_title_link.html.twig');
        $description = TextareaField::new('description');
        $slug = TextField::new('slug');
        $profile = AssociationField::new('profile');
        $date = DateTimeField::new('date');
        $config = PublicationConfigField::new('config');
        $ownerId = TextField::new('ownerId');
        $clientAnnotations = TextareaField::new('clientAnnotations');
        $zippyId = TextField::new('zippyId');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $createdAt = DateTimeField::new('createdAt');
        $zippyHash = TextField::new('zippyHash');
        $configEnabled = Field::new('config.enabled');
        $configDownloadViaEmail = Field::new('config.downloadViaEmail');
        $configIncludeDownloadTermsInZippy = Field::new('config.includeDownloadTermsInZippy');
        $configUrls = ArrayField::new('config.urls');
        $configCopyrightText = TextareaField::new('config.copyrightText');
        $configCss = TextareaField::new('config.css');
        $configLayout = TextField::new('config.layout');
        $configTheme = TextField::new('config.theme');
        $configPubliclyListed = Field::new('config.publiclyListed');
        $configDownloadEnabled = Field::new('config.downloadEnabled');
        $configBeginsAt = DateTimeField::new('config.beginsAt');
        $configExpiresAt = DateTimeField::new('config.expiresAt');
        $configSecurityMethod = SecurityMethodChoiceField::new('config.securityMethod');
        $configSecurityOptions = ArrayField::new('config.securityOptions');
        $configMapOptions = MapOptionsField::new('config.mapOptions');
        $configLayoutOptions = LayoutOptionsField::new('config.layoutOptions');
        $configTermsText = TextareaField::new('config.terms.text');
        $configTermsUrl = TextField::new('config.terms.url');
        $configDownloadTermsText = TextareaField::new('config.downloadTerms.text');
        $configDownloadTermsUrl = TextField::new('config.downloadTerms.url');
        $assets = AssociationField::new('assets');
        $package = AssociationField::new('package');
        $cover = AssociationField::new('cover');
        $children = AssociationField::new('children');
        $childrenCount = IntegerField::new('children.count', 'Children');
        $assetsCount = IntegerField::new('assets.count', 'Assets');
        $publiclyListed = BooleanField::new('publiclyListed')->renderAsSwitch(false);
        $enabled = BooleanField::new('enabled')->renderAsSwitch(false);
        $securityMethod = TextareaField::new('securityMethod');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $slug, $profile, $parent, $childrenCount, $assetsCount, $publiclyListed, $enabled, $securityMethod, $createdAt];
        }
//        elseif (Crud::PAGE_DETAIL === $pageName) {
//            return [$id, $title, $description, $ownerId, $slug, $date, $createdAt, $zippyId, $zippyHash, $clientAnnotations, $configEnabled, $configDownloadViaEmail, $configIncludeDownloadTermsInZippy, $configUrls, $configCopyrightText, $configCss, $configLayout, $configTheme, $configPubliclyListed, $configDownloadEnabled, $configBeginsAt, $configExpiresAt, $configSecurityMethod, $configSecurityOptions, $configMapOptions /*, $configLayoutOptions */, $configTermsText, $configTermsUrl, $configDownloadTermsText, $configDownloadTermsUrl, $assets, $profile, $package, $cover, $parent, $children];
//        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        }

        return [];
    }

    public function permissions(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Publication $publication */
        $publication = $adminContext->getEntity()->getInstance();
        $id = $publication->getId();

        $twigParameters = $this->permissionView->getViewParameters(
            $this->permissionView->getObjectKey(Publication::class),
            $id
        );
        $twigParameters['back_url'] = $adminUrlGenerator->get('referrer');

        return $this->render('@AlchemyAcl/permissions/entity/acl.html.twig', $twigParameters);
    }
}
