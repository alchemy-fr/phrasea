<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Admin\Field\TagGroupChoiceField;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Field\MetadataTagBlockField;
use App\Field\MetadataTagField;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
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
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;


class AttributeDefinitionCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly AttributeTypeRegistry $typeRegistry,
        // private readonly MetadataManipulator $metadataManipulator
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return AttributeDefinition::class;
    }

    public function configureAssets($assets): Assets
    {
        return parent::configureAssets($assets)
            ->addWebpackEncoreEntry('admin')
            ;
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

        // $tags = $this->metadataManipulator->getKnownTagGroups();

        $workspace = AssociationField::new('workspace')->setColumns(6);
        $class = AssociationField::new('class')->setColumns(6);
        $name = TextField::new('name')->setColumns(6);
        $fieldType = ChoiceField::new('fieldType')->setChoices($fileTypeChoices)->setColumns(6);
        $fileType = TextField::new('fileType');

        $allowInvalid = BooleanField::new('allowInvalid')->renderAsSwitch(false)->setColumns(2);
        $translatable = BooleanField::new('translatable')->renderAsSwitch(false)->setColumns(2);
        $sortable = BooleanField::new('sortable')->setColumns(2);
        $multiple = BooleanField::new('multiple')->renderAsSwitch(false)->setColumns(2);
        $searchable = BooleanField::new('searchable')->renderAsSwitch(false)->setColumns(2);

        $brk = FormField::addRow();

        $searchBoost = IntegerField::new('searchBoost');

        $initialValuesSource = AssociationField::new('initialValuesSource')
            ->addCssClass('initialValuesSource')
            ->setQueryBuilder(
                function (QueryBuilder $queryBuilder) {
                  $queryBuilder->getQuery();
                }
            )
            ->autocomplete()
            ->setCrudController(MetadataTagController::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'multiple' => false,
                'row_attr' => [
                    'data-controller' => 'initialValuesSource', // initialValuesAll',
                    'data-action' => 'initialValuesSource:tagChanged->initialValuesAll#tagChanged'
                ],
                'attr' => [
                    'data-initialValuesSource-target' => 'input',
                    'data-action' => 'initialValuesSource#render',
                ],
            ]);

        $initialValuesAdvanced = BooleanField::new('advanced')
            ->renderAsSwitch(false)
            ->addCssClass('advancedFieldSource')
            ->setFormTypeOptions([
                'mapped' => false,
                'row_attr' => [
                    'data-controller' => 'initialValuesAdvanced',
                ],
                'attr' => [
                    'data-initialValuesAdvanced-target' => 'input',
                    'data-action' => 'initialValuesAdvanced#render',
                ],
            ]);

        $initialValuesAll = TextareaField::new('initialValuesAll')
            ->addCssClass("initialValuesAll")
            ->setFormTypeOptions([
                'row_attr' => [
                    'data-controller' => 'initialValuesAll',
                ],
                'attr' => [
                    'data-initialValuesAll-target' => 'input',
                    'data-action' => 'initialValuesAll#render',
                ],
            ]);

        $fallbackAll = TextareaField::new('fallbackAll')->setHelp('e.g. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackEN = TextareaField::new('fallbackEN', 'Fallback value template EN')->setHelp('e.g. Dimensions are: {{ file.width }}x{{ file.height }}');
        $fallbackFR = TextareaField::new('fallbackFR', 'Fallback value template FR')->setHelp('ex. Les dimensions sont : {{ file.width }}x{{ file.height }}');
        $id = IdField::new();
        $slug = TextField::new('slug');
        $facetEnabled = Field::new('facetEnabled');
        $fallback = ArrayField::new('fallback');
        $key = TextField::new('key');
        $position = IntegerField::new('position');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $attributes = AssociationField::new('attributes');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $class, $name, $fieldType, $multiple, $facetEnabled, $sortable, $searchable, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $fileType, $fieldType, $searchable, $facetEnabled, $sortable, $translatable, $multiple, $allowInvalid, $searchBoost, $fallback, $key, $position, $createdAt, $updatedAt, $workspace, $class, $attributes];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $class, $name, $fieldType, $fileType,
                $allowInvalid, $sortable, $translatable, $multiple, $searchable, $brk,
                $searchBoost,
                $initialValuesSource, $initialValuesAdvanced, $initialValuesAll,
                $fallbackAll, $fallbackEN, $fallbackFR];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $class, $name, $fieldType, $fileType,
                $allowInvalid, $sortable, $translatable, $multiple, $searchable, $brk,
                $searchBoost,
                $initialValuesSource, $initialValuesAdvanced, $initialValuesAll,
                $fallbackAll, $fallbackEN, $fallbackFR];
        }

        return [];
    }
}
