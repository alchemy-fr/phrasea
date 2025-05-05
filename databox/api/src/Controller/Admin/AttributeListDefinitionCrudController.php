<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\AttributeList\AttributeListDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AttributeListDefinitionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeListDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute List Definition')
            ->setEntityLabelInPlural('Attribute List Definitions')
            ->setSearchFields(['id', 'definition', 'list'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['list' => 'ASC', 'position' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('list'))
            ->add(AssociationIdentifierFilter::new('definition'))
            ->add('builtIn');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield AssociationField::new('list');
        yield AssociationField::new('definition');
        yield TextField::new('builtIn');
        yield NumberField::new('position');
    }
}
