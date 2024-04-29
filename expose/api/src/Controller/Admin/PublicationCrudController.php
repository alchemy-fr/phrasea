<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Publication;
use App\Field\PublicationConfigField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
        yield AssociationField::new('parent')
            ->setRequired(false);
        yield TextField::new('title')
            ->setTemplatePath('admin/list/publication_title_link.html.twig');
        yield TextareaField::new('description')
            ->hideOnIndex();
        yield TextField::new('slug');
        yield AssociationField::new('profile')
            ->setRequired(false);
        yield DateTimeField::new('date');
        yield PublicationConfigField::new('config')
            ->hideOnIndex()
        ;
        yield TextField::new('ownerId');
        yield JsonField::new('clientAnnotations')
            ->hideOnIndex();
        yield TextField::new('zippyId')
            ->hideOnIndex();
        yield IdField::new();
        yield DateTimeField::new('createdAt')
        ->hideOnForm();
        yield JsonField::new('translations');
        yield IntegerField::new('children.count', 'Children');
        yield IntegerField::new('assets.count', 'Assets');
        yield BooleanField::new('publiclyListed')
            ->renderAsSwitch(false);
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield TextareaField::new('securityMethod');
    }
}
