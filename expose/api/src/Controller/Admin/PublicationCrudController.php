<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PublicationCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Publication::class;
    }

    public function __construct(PermissionView $permissionView)
    {
        $this->setPermissionView($permissionView);
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
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');
        $childrenCount = IntegerField::new('children.count', 'Children');
        $assetsCount = IntegerField::new('assets.count', 'Assets');
        $publiclyListed = BooleanField::new('publiclyListed')->renderAsSwitch(false);
        $enabled = BooleanField::new('enabled')->renderAsSwitch(false);
        $securityMethod = TextareaField::new('securityMethod');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $slug, $profile, $parent, $childrenCount, $assetsCount, $publiclyListed, $enabled, $securityMethod, $createdAt];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$parent, $title, $description, $slug, $profile, $date, $config, $ownerId, $clientAnnotations, $zippyId];
        }

        return [];
    }
}
