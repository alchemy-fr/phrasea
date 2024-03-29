<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkspaceCrudController extends AbstractAclAdminCrudController
{
    public function __construct(private readonly UserChoiceField $userChoiceField)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Workspace::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workspace')
            ->setEntityLabelInPlural('Workspace')
            ->setSearchFields(['id', 'name', 'slug', 'ownerId', 'config', 'enabledLocales', 'localeFallbacks'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();
        $name = TextField::new('name');
        $slug = TextField::new('slug');
        $isPublic = BooleanField::new('public')
            ->setHelp('If you need to expose a collection publicly, then its workspace has to be public.');
        $ownerId = TextField::new('ownerId');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $enabledLocales = ArrayField::new('enabledLocales');
        $localeFallbacks = ArrayField::new('localeFallbacks');
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
            return [$id, $name, $slug, $enabledLocales, $localeFallbacks, $isPublic, $updatedAt, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $name, $slug, $ownerId, $enabledLocales, $localeFallbacks, $isPublic, $createdAt, $updatedAt, $deletedAt, $collections, $tags, $renditionClasses, $renditionDefinitions, $attributeDefinitions, $files];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$name, $slug, $ownerUser, $enabledLocales, $localeFallbacks, $isPublic];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$name, $slug, $ownerUser, $enabledLocales, $localeFallbacks, $isPublic];
        }

        return [];
    }
}
