<?php

namespace App\Controller\Admin;

use App\Entity\Publication;
use App\Field\PublicationConfigField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AclBundle\Admin\PermissionView;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->setSearchFields([
                'title',
                'slug',
                'ownerId',
                'date',
            ])
            ->setEntityLabelInSingular('Publication')
            ->setEntityLabelInPlural('Publication');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('id'))
            ->add(TextFilter::new('title'))
            ->add(TextFilter::new('ownerId'))
            ->add(EntityFilter::new('profile'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('title')
            ->setTemplatePath('admin/list/publication_title_link.html.twig');
        yield TextField::new('slug');

        yield AssociationField::new('parent')
            ->setRequired(false);

        yield TextField::new('ownerId');

        yield TextareaField::new('description')
            ->hideOnIndex();
        yield AssociationField::new('profile')
            ->setRequired(false);
        yield DateTimeField::new('date');
        yield PublicationConfigField::new('config')
            ->hideOnIndex()
            ->hideOnDetail()
        ;
        yield JsonField::new('clientAnnotations')
            ->hideOnIndex();
        yield TextField::new('zippyId')
            ->hideOnIndex();
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield IntegerField::new('assets.count', 'Assets')
            ->onlyOnIndex();
        yield BooleanField::new('publiclyListed')
            ->onlyOnIndex()
            ->renderAsSwitch(false);
        yield BooleanField::new('enabled')
            ->onlyOnIndex()
            ->renderAsSwitch(false);
        yield TextareaField::new('securityMethod')
            ->onlyOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
