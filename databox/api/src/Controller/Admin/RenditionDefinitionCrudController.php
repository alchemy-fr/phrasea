<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Core\RenditionDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class RenditionDefinitionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionDefinition::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('RenditionDefinition')
            ->setEntityLabelInPlural('RenditionDefinition')
            ->setSearchFields(['id', 'name', 'definition', 'priority'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('name');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield AssociationField::new('class');
        yield AssociationField::new('workspace');
        yield TextField::new('key')
            ->hideOnIndex()
        ;
        yield Field::new('pickSourceFile')
        ->hideOnIndex();
        yield BooleanField::new('useAsOriginal');
        yield BooleanField::new('useAsPreview');
        yield BooleanField::new('useAsThumbnail');
        yield Field::new('useAsThumbnailActive', 'Thumb Active')
            ->hideOnIndex();
        yield IntegerField::new('priority');
        yield Field::new('download')
            ->hideOnIndex()
        ;
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();

        yield JsonField::new('labels')
            ->hideOnForm()
            ->hideOnIndex()
        ;
    }
}
