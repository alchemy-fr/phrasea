<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AttributeDefinitionCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly AttributeTypeRegistry $typeRegistry)
    {
    }

    public static function getEntityFqcn(): string
    {
        return AttributeDefinition::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute Definition')
            ->setEntityLabelInPlural('Attribute Definitions')
            ->setSearchFields(['id', 'name', 'slug', 'fileType', 'fieldType', 'searchBoost', 'fallback', 'key', 'position'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add('searchable')
            ->add('multiple')
            ->add('fieldType')
            ->add('fileType');
    }

    public function configureFields(string $pageName): iterable
    {
        $fileTypeChoices = [];
        foreach ($this->typeRegistry->getTypes() as $name => $type) {
            $fileTypeChoices[$name] = $name;
        }

        yield IdField::new();
        yield TextField::new('name');
        yield TextField::new('slug');
        yield AssociationField::new('workspace');
        yield AssociationField::new('class');
        yield TextField::new('fileType');
        yield ChoiceField::new('fieldType')
            ->setChoices($fileTypeChoices);
        yield BooleanField::new('allowInvalid')
            ->hideOnIndex()
            ->renderAsSwitch(false);
        yield BooleanField::new('translatable')
            ->hideOnIndex()
            ->renderAsSwitch(false);
        yield BooleanField::new('sortable')
            ->hideOnIndex();
        yield BooleanField::new('multiple')
            ->renderAsSwitch(false);
        yield BooleanField::new('searchable')
            ->hideOnIndex()
            ->renderAsSwitch(false);
        yield BooleanField::new('suggest')
            ->hideOnIndex()
            ->renderAsSwitch(false);
        yield IntegerField::new('searchBoost')
            ->hideOnIndex();
        yield TextareaField::new('initialValuesAll')
            ->hideOnIndex();
        yield TextareaField::new('fallbackAll')
            ->hideOnIndex()
            ->setHelp('e.g. Dimensions are: {{ file.width }}x{{ file.height }}');
        yield TextareaField::new('fallbackEN', 'Fallback value template EN')
            ->hideOnIndex()
            ->setHelp('e.g. Dimensions are: {{ file.width }}x{{ file.height }}');
        yield TextareaField::new('fallbackFR', 'Fallback value template FR')
            ->hideOnIndex()
            ->setHelp('ex. Les dimensions sont : {{ file.width }}x{{ file.height }}');
        yield Field::new('facetEnabled')
            ->hideOnIndex();
        yield ArrayField::new('fallback')
            ->hideOnIndex();
        yield TextField::new('key')
            ->hideOnIndex();
        yield IntegerField::new('position');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield JsonField::new('labels')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        return [];
    }
}
