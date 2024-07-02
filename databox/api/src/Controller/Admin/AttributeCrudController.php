<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\ChildPropertyEntityFilter;
use App\Entity\Core\Attribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AttributeCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attribute::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChildPropertyEntityFilter::new('definition', 'workspace', 'Workspace'))
            ->add('value')
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
        yield Field::new('locked');
        yield IntegerField::new('origin');
        yield TextField::new('originVendor')
            ->hideOnIndex();
        yield TextareaField::new('originVendorContext');
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
