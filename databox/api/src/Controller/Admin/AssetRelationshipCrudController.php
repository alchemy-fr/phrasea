<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Core\AssetRelationship;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AssetRelationshipCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetRelationship::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset Relationship')
            ->setEntityLabelInPlural('Asset Relationships')
            ->setSearchFields(['id', 'type'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('source'))
            ->add(AssociationIdentifierFilter::new('target'))
            ->add(AssociationIdentifierFilter::new('sourceFile'))
            ->add(EntityFilter::new('integration'))
            ->add(TextFilter::new('type'))
            ->add(BooleanFilter::new('sticky'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('type');
        yield BooleanField::new('sticky')
            ->renderAsSwitch(false);
        yield AssociationField::new('source');
        yield AssociationField::new('target');
        yield AssociationField::new('sourceFile')
            ->onlyOnDetail();
        yield AssociationField::new('integration')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
    }
}
