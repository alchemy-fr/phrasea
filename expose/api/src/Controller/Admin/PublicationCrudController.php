<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Publication;
use App\Entity\PublicationConfig;
use App\Field\PublicationConfigField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PublicationCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Publication::class;
    }

    public function __construct(
        PermissionView $permissionView,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
        $this->setPermissionView($permissionView);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields([
                'title',
                'slug',
                'ownerId',
            ])
            ->setEntityLabelInSingular('Publication')
            ->setEntityLabelInPlural('Publications');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('id'))
            ->add(TextFilter::new('title'))
            ->add(TextFilter::new('slug'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(EntityFilter::new('profile'))
            ->add(AssociationIdentifierFilter::new('parent'))
            ->add(DateTimeFilter::new('date'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(BooleanFilter::new('config.enabled', 'Enabled (own value, empty = inherited from the profile)'))
            ->add(BooleanFilter::new('config.publiclyListed', 'Publicly listed (own value, empty = inherited from the profile)'))
            ->add(ChoiceFilter::new('config.securityMethod', 'Security method')->setChoices(PublicationConfig::getSecurityMethodChoices()))
            ->add(DateTimeFilter::new('config.beginsAt', 'Begins at'))
            ->add(DateTimeFilter::new('config.expiresAt', 'Expires at'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('title')
            ->setTemplatePath('admin/list/publication_title_link.html.twig');
        yield TextField::new('slug');

        yield AssociationField::new('parent')
            ->autocomplete()
            ->setRequired(false);

        yield TextField::new('ownerId');

        yield TextareaField::new('description')
            ->hideOnIndex();
        yield AssociationField::new('profile')
            ->autocomplete()
            ->setRequired(false);
        yield DateTimeField::new('date');
        yield PublicationConfigField::new('config')
            ->hideOnIndex()
            ->hideOnDetail()
        ;
        yield JsonField::new('clientAnnotations')
            ->hideOnIndex();
        yield TextField::new('archivePath')
            ->hideOnIndex()
            ->hideOnForm();
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield BooleanField::new('publiclyListed')
            ->onlyOnIndex()
            ->renderAsSwitch(false);
        yield BooleanField::new('enabled')
            ->onlyOnIndex()
            ->renderAsSwitch(false);
        yield TextField::new('securityMethod')
            ->onlyOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
