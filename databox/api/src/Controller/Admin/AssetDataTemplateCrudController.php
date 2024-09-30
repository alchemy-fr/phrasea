<?php

namespace App\Controller\Admin;

use App\Admin\Field\PrivacyField;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Template\AssetDataTemplate;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;

class AssetDataTemplateCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetDataTemplate::class;
    }

    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly PrivacyField $privacyField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Data Template')
            ->setEntityLabelInPlural('Data Templates')
            ->setSearchFields(['id', 'name', 'ownerId'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('name'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield BooleanField::new('public');
        yield AssociationField::new('collection');
        yield BooleanField::new('includeCollectionChildren', 'Include children');
        yield AssociationField::new('workspace');
        yield $this->privacyField->create('privacy');
        yield AssociationField::new('tags')
            ->hideOnIndex();
        yield $this->userChoiceField->create('ownerId', 'Owner')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield AssociationField::new('attributes');

    }
}
