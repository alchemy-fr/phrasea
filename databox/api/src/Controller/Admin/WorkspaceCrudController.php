<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkspaceCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Workspace::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $permissionsAction = Action::new('permissions')
            ->linkToRoute(
                'admin_global_permissions',
                [
                    'type' => 'workspace',
                ]
            )
            ->createAsGlobalAction()
        ;

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $permissionsAction)
            ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workspace')
            ->setEntityLabelInPlural('Workspace')
            ->setSearchFields(['id', 'name', 'slug', 'ownerId', 'config', 'enabledLocales', 'localeFallbacks'])
            ->setPaginatorPageSize(100)
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $slug = TextField::new('slug');
        $ownerId = TextField::new('ownerId');
        $enabledLocales = ArrayField::new('enabledLocales');
        $localeFallbacks = ArrayField::new('localeFallbacks');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $config = TextField::new('config');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $deletedAt = DateTimeField::new('deletedAt');
        $collections = AssociationField::new('collections');
        $tags = AssociationField::new('tags');
        $renditionClasses = AssociationField::new('renditionClasses');
        $renditionDefinitions = AssociationField::new('renditionDefinitions');
        $attributeDefinitions = AssociationField::new('attributeDefinitions');
        $files = AssociationField::new('files');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $slug, $enabledLocales, $localeFallbacks, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $ownerId, $config, $enabledLocales, $localeFallbacks, $createdAt, $updatedAt, $deletedAt, $collections, $tags, $renditionClasses, $renditionDefinitions, $attributeDefinitions, $files];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $slug, $ownerId, $enabledLocales, $localeFallbacks];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $slug, $ownerId, $enabledLocales, $localeFallbacks];
        }
        return [];
    }
}
