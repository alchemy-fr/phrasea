<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Admin\Field\TagGroupChoiceField;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
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
        private readonly MetadataManipulator $metadataManipulator
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

        $tags = $this->metadataManipulator->getKnownTagGroups();

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

        $brk = FormField::addRow();

        $searchBoost = IntegerField::new('searchBoost');

        $tags = array_slice($tags, 0, 100, true);
 //       $tags['template'] = '__template__';
 //       $fieldSource = TagGroupChoiceField::new('fieldSource')
/*
        $initialValuesSource = ChoiceField::new('initialValuesSource')
//            ->setChoices($tags)
            ->setChoices(static fn (?AttributeDefinition $foo): array => $foo->getTagsList($this->metadataManipulator))
            ->autocomplete()
            ->addCssClass("initialValuesSource")
            //      ->addJsFiles("admin")
            ->setFormTypeOptions([
                'mapped' => false,
                'row_attr' => [
                    'data-controller' => 'initialValuesSource', // initialValuesAll',
                    'data-action' => 'initialValuesSource:tagChanged->initialValuesAll#tagChanged'
                ],
                'attr' => [
                    'data-initialValuesSource-target' => 'input',
                    'data-action' => 'initialValuesSource#render',
                ],
            ]);
*/
        $initialValuesSource = ChoiceField::new('initialValuesSource')
            ->setChoices($tags)
            ->autocomplete()
            ->addCssClass("initialValuesSource")
            //      ->addJsFiles("admin")
            ->setFormTypeOptions([
                'mapped' => false,
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

//        $truc = TextareaField::new('TRUC')
//            ->setLabel(false)
//            ->addCssClass("truc")
//            ->setFormTypeOptions([
//                'mapped' => false,
//                'row_attr' => [
//                    'data-controller' => 'snarkdown',
//                ],
//                'attr' => [
//                    'data-snarkdown-target' => 'input',
//                    'data-action' => 'snarkdown#render',
//                ],
//            ]);

        $initialValuesAll = TextareaField::new('initialValuesAll')
            ->addCssClass("initialValuesAll")
            ->setFormTypeOptions([
//                'mapped' => false,
                'row_attr' => [
                    'data-controller' => 'initialValuesAll',
//                    'data-action' => 'initialValuesSource:tagChanged->initialValuesAll#tagChanged'
                ],
                'attr' => [
                    'data-initialValuesAll-target' => 'input',
                    'data-action' => 'initialValuesAll#render',
                ],
            ]);

//        $initialValuesAll = TextareaField::new('initialValuesAll')
//            ->addCssClass("initialValuesAll")
//            ->setFormTypeOptions([
//                'row_attr' => [
//                    'data-controller' => 'initialValuesAll',
//                ],
//                'attr' => [
//                    'data-initialValuesAll-target' => 'input',
//                    'data-action' => 'initialValuesAll#render',
//                ],
//        ]);

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
            return [$id, $workspace, $class, $name, $fileType, $fieldType, $multiple, $facetEnabled, $sortable, $searchable, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $fileType, $fieldType, $searchable, $facetEnabled, $sortable, $translatable, $multiple, $allowInvalid, $searchBoost, $fallback, $key, $position, $createdAt, $updatedAt, $workspace, $class, $attributes];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType,
                $allowInvalid, $sortable, $translatable, $multiple, $searchable, $brk,
                $searchBoost,
                $initialValuesSource, $initialValuesAdvanced, $initialValuesAll,
                $fallbackAll, $fallbackEN, $fallbackFR];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $class, $name, $fileType, $fieldType,
                $allowInvalid, $sortable, $translatable, $multiple, $searchable, $brk,
                $searchBoost,
                $initialValuesSource, $initialValuesAdvanced, $initialValuesAll,
                $fallbackAll, $fallbackEN, $fallbackFR];
        }

        return [];
    }
}
