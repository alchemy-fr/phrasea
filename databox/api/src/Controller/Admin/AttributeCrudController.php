<?php

namespace App\Controller\Admin;

use App\Entity\Core\Attribute;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Alchemy\AdminBundle\Filter\ChildPropertyEntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class AttributeCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attribute::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChildPropertyEntityFilter::new('definition', 'workspace', 'Workspace'))
            ->add(TextFilter::new('value'))
            ->add(TextFilter::new('locale'))
            ->add(BooleanFilter::new('locked'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute')
            ->setEntityLabelInPlural('Attribute')
            ->setSearchFields(['id', 'locale', 'position', 'translationId', 'translationOriginHash', 'value', 'origin', 'originVendor', 'originUserId', 'originVendorContext', 'coordinates', 'status', 'confidence'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('definition');
        yield AssociationField::new('asset');
        yield TextField::new('locale');
        yield TextField::new('value');
        yield BooleanField::new('locked');
        yield IntegerField::new('origin');
        yield TextField::new('originVendor');
        yield TextareaField::new('originVendorContext')
            ->hideOnIndex();
        yield IntegerField::new('position');
        yield IdField::new('originUserId')
            ->hideOnIndex();
        yield JsonField::new('assetAnnotations')
            ->hideOnIndex();
        yield IntegerField::new('status')
            ->hideOnIndex();
        yield NumberField::new('confidence')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
