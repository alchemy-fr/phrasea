<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Integration\WorkspaceIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class IntegrationCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkspaceIntegration::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'title', 'integration', 'config']);
    }

    public function configureFields(string $pageName): iterable
    {
        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $integration = TextField::new('integration');
        $optionsYaml = Field::new('optionsYaml');
        $enabled = Field::new('enabled');
        $id = IdField::new('id', 'ID');
        $config = TextField::new('config');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $that = TextareaField::new('this')->setTemplatePath('admin/integration_config_info.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$enabled, $title, $integration, $workspace, $createdAt, $that, $updatedAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $integration, $enabled, $config, $createdAt, $updatedAt, $workspace];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled];
        }

        return [];
    }
}
