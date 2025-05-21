<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\AttributeList\AttributeListItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AttributeListItemCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeListItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute List Item')
            ->setEntityLabelInPlural('Attribute List Items')
            ->setSearchFields(['id', 'definition', 'list', 'key'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['list' => 'ASC', 'position' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('list'))
            ->add(AssociationIdentifierFilter::new('definition'))
            ->add('key')
            ->add('type')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('list');
        yield AssociationField::new('definition');
        yield ChoiceField::new('type')
            ->setChoices(AttributeListItem::TYPES);;
        yield TextField::new('key');
        yield BooleanField::new('displayEmpty');
        yield TextField::new('format');
        yield NumberField::new('position');
    }
}
