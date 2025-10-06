<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Core\RenditionDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class RenditionDefinitionCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Rendition Definition')
            ->setEntityLabelInPlural('Rendition Definitions')
            ->setSearchFields(['id', 'name', 'definition', 'priority'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['workspace.name' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(EntityFilter::new('policy'))
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('substitutable'))
            ->add(BooleanFilter::new('useAsOriginal'))
            ->add(BooleanFilter::new('useAsPreview'))
            ->add(BooleanFilter::new('useAsThumbnail'))
            ->add(BooleanFilter::new('useAsThumbnailActive', 'Thumb Active'))
            ->add(ChoiceFilter::new('buildMode')
                ->setChoices(RenditionDefinition::BUILD_MODE_CHOICES)
            )
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield AssociationField::new('parent');
        yield AssociationField::new('policy');
        yield AssociationField::new('workspace');
        yield TextField::new('key')
            ->hideOnIndex()
        ;
        yield BooleanField::new('substitutable')
            ->hideOnIndex();
        yield ChoiceField::new('buildMode')
            ->setChoices(RenditionDefinition::BUILD_MODE_CHOICES);
        yield CodeEditorField::new('definition')
            ->setLanguage('yaml')
            ->setNumOfRows(20)
            ->setRequired(false)
            ->hideOnIndex();
        yield BooleanField::new('useAsOriginal')
            ->hideOnIndex();
        yield BooleanField::new('useAsPreview')
            ->hideOnIndex();
        yield BooleanField::new('useAsThumbnail')
            ->hideOnIndex();
        yield Field::new('useAsThumbnailActive', 'Thumb Active')
            ->hideOnIndex();
        yield IntegerField::new('priority');
        yield BooleanField::new('download')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();

        yield JsonField::new('labels')
            ->hideOnForm()
            ->hideOnIndex()
        ;
        yield ChoiceField::new('target');
    }
}
