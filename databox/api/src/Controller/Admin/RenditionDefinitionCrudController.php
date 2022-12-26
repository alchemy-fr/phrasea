<?php

namespace App\Controller\Admin;

use App\Entity\Core\RenditionDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class RenditionDefinitionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RenditionDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('RenditionDefinition')
            ->setEntityLabelInPlural('RenditionDefinition')
            ->setSearchFields(['id', 'name', 'definition', 'priority'])
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
            ->add('name');
    }

    public function configureFields(string $pageName): iterable
    {
        $workspace = AssociationField::new('workspace');
        $name = TextField::new('name');
        $class = AssociationField::new('class');
        $pickSourceFile = Field::new('pickSourceFile');
        $useAsOriginal = Field::new('useAsOriginal');
        $useAsPreview = Field::new('useAsPreview');
        $useAsThumbnail = Field::new('useAsThumbnail');
        $useAsThumbnailActive = Field::new('useAsThumbnailActive', 'Thumb Active');
        $priority = IntegerField::new('priority');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $download = Field::new('download');
        $definition = TextareaField::new('definition');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $renditions = AssociationField::new('renditions');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $workspace, $name, $class, $pickSourceFile, $useAsOriginal, $useAsPreview, $useAsThumbnail, $useAsThumbnailActive, $priority, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $download, $pickSourceFile, $useAsOriginal, $useAsPreview, $useAsThumbnail, $useAsThumbnailActive, $definition, $priority, $createdAt, $updatedAt, $workspace, $class, $renditions];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$workspace, $name, $class, $pickSourceFile, $useAsOriginal, $useAsPreview, $useAsThumbnail, $useAsThumbnailActive, $priority];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$workspace, $name, $class, $pickSourceFile, $useAsOriginal, $useAsPreview, $useAsThumbnail, $useAsThumbnailActive, $priority];
        }
    }
}
