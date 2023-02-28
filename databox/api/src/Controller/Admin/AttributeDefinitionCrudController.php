<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AttributeDefinitionCrudController extends AbstractAdminCrudController
{
    private AttributeTypeRegistry $typeRegistry;

    public function __construct(AttributeTypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public static function getEntityFqcn(): string
    {
        return AttributeDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('AttributeDefinition')
            ->setEntityLabelInPlural('AttributeDefinition')
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

        $workspace = AssociationField::new('workspace');
        $class = AssociationField::new('class');
        $name = TextField::new('name');
        $fileType = TextField::new('fileType');
        $fieldType = ChoiceField::new('fieldType')->setChoices($fileTypeChoices);
        $allowInvalid = BooleanField::new('allowInvalid')->renderAsSwitch(false);
        $translatable = BooleanField::new('translatable')->renderAsSwitch(false);
        $sortable = BooleanField::new('sortable');
        $multiple = BooleanField::new('multiple')->renderAsSwitch(false);
        $searchable = BooleanField::new('searchable')->renderAsSwitch(false);
        $searchBoost = IntegerField::new('searchBoost');
        $fallbackAll = TextareaField::new('fallbackAll')->setHelp('i.e. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackEN = TextareaField::new('fallbackEN', 'Fallback value template EN')->setHelp('i.e. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackFR = TextareaField::new('fallbackFR', 'Fallback value template FR')->setHelp('ex. Les dimensions sont : {{ file.width }}x{{ file.height }}');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $slug = TextField::new('slug');
        $facetEnabled = Field::new('facetEnabled');
        $fallback = ArrayField::new('fallback');
        $key = TextField::new('key');
        $position = IntegerField::new('position');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $attributes = AssociationField::new('attributes');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $class, $name, $fileType, $fieldType, $multiple, $facetEnabled, $sortable, $searchable, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $fileType, $fieldType, $searchable, $facetEnabled, $sortable, $translatable, $multiple, $allowInvalid, $searchBoost, $fallback, $key, $position, $createdAt, $updatedAt, $workspace, $class, $attributes];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType, $allowInvalid, $sortable, $translatable, $multiple, $searchable, $searchBoost, $fallbackAll, $fallbackEN, $fallbackFR];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType, $allowInvalid, $sortable, $translatable, $multiple, $searchable, $searchBoost, $fallbackAll, $fallbackEN, $fallbackFR];
        }

        return [];
    }
}
