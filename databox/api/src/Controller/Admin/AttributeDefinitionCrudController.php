<?php

namespace App\Controller\Admin;

use App\Entity\Core\AttributeDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AttributeDefinitionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttributeDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AttributeDefinition')
            ->setEntityLabelInPlural('AttributeDefinition')
            ->setSearchFields(['id', 'name', 'slug', 'fileType', 'fieldType', 'searchBoost', 'fallback', 'key', 'position'])
            ->setPaginatorPageSize(100)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
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
        $workspace = AssociationField::new('workspace');
        $class = AssociationField::new('class');
        $name = TextField::new('name');
        $fileType = TextField::new('fileType');
        $fieldType = TextField::new('fieldType');
        $allowInvalid = Field::new('allowInvalid');
        $translatable = Field::new('translatable');
        $multiple = BooleanField::new('multiple');
        $searchable = BooleanField::new('searchable');
        $searchBoost = IntegerField::new('searchBoost');
        $fallbackAll = Field::new('fallbackAll')->setHelp('i.e. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackEN = Field::new('fallbackEN', 'Fallback value template EN')->setHelp('i.e. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackFR = Field::new('fallbackFR', 'Fallback value template FR')->setHelp('ex. Les dimensions sont : {{ file.width }}x{{ file.height }}');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $slug = TextField::new('slug');
        $facetEnabled = Field::new('facetEnabled');
        $fallback = ArrayField::new('fallback');
        $key = TextField::new('key');
        $position = IntegerField::new('position');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $attributes = AssociationField::new('attributes');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $class, $name, $fileType, $fieldType, $multiple, $facetEnabled, $searchable, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $fileType, $fieldType, $searchable, $facetEnabled, $translatable, $multiple, $allowInvalid, $searchBoost, $fallback, $key, $position, $createdAt, $updatedAt, $workspace, $class, $attributes];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType, $allowInvalid, $translatable, $multiple, $searchable, $searchBoost, $fallbackAll, $fallbackEN, $fallbackFR];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType, $allowInvalid, $translatable, $multiple, $searchable, $searchBoost, $fallbackAll, $fallbackEN, $fallbackFR];
        }
    }
}
