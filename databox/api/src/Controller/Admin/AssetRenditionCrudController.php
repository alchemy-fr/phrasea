<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\ChildPropertyEntityFilter;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;

class AssetRenditionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetRendition::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AssetRendition')
            ->setEntityLabelInPlural('AssetRendition')
            ->setSearchFields(['id', 'definition.name'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChildPropertyEntityFilter::new(
                'definition',
                'workspace',
                Workspace::class,
                'Workspace'
            ))
            ->add(EntityFilter::new('definition'))
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add(NullFilter::new('file', 'Is Ready')->setChoiceLabels('Not ready', 'Ready'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(BooleanFilter::new('locked'))
            ->add(BooleanFilter::new('substituted'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('asset');
        yield AssociationField::new('definition');
        yield CodeField::new('buildHash')
            ->hideOnIndex();
        yield JsonField::new('moduleHashes')
            ->hideOnIndex();
        yield AssociationField::new('file');
        yield BooleanField::new('ready')
            ->renderAsSwitch(false);
        yield BooleanField::new('locked');
        yield BooleanField::new('substituted');
        yield BooleanField::new('projection')
            ->renderAsSwitch(false);
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
