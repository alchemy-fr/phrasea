<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\ChildPropertyEntityFilter;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AttributeCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attribute::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChildPropertyEntityFilter::new(
                'definition',
                'workspace',
                Workspace::class,
                'Workspace'
            ))
            ->add(EntityFilter::new('definition')->autocomplete())
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add(TextFilter::new('value'))
            ->add(TextFilter::new('locale'))
            ->add(ChoiceFilter::new('origin')->setChoices(array_flip(Attribute::ORIGIN_LABELS)))
            ->add(TextFilter::new('originVendor'))
            ->add(ChoiceFilter::new('status')->setChoices(array_flip(Attribute::STATUS_LABELS)))
            ->add(BooleanFilter::new('locked'))
            ->add(BooleanFilter::new('invalid'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'))
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute')
            ->setEntityLabelInPlural('Attributes')
            ->setSearchFields(['id', 'locale', 'position', 'value', 'origin', 'originVendor', 'originUserId', 'originVendorContext', 'status', 'confidence'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('definition')
            ->autocomplete();
        yield AssociationField::new('asset')
            ->autocomplete()
        ;
        yield TextField::new('locale');
        yield TextField::new('value');
        yield BooleanField::new('locked');
        yield ChoiceField::new('origin')
            ->setChoices(array_flip(Attribute::ORIGIN_LABELS));
        yield TextField::new('originVendor');
        yield TextareaField::new('originVendorContext')
            ->hideOnIndex();
        yield IntegerField::new('position')
            ->hideOnIndex();
        yield BooleanField::new('invalid');
        yield IdField::new('originUserId')
            ->hideOnIndex();
        yield JsonField::new('assetAnnotations')
            ->hideOnIndex();
        yield ChoiceField::new('status')
            ->setChoices(array_flip(Attribute::STATUS_LABELS));
        yield NumberField::new('confidence')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
