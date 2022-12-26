<?php

namespace App\Controller\Admin;

use App\Entity\Integration\WorkspaceIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class IntegrationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkspaceIntegration::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['id', 'title', 'integration', 'config'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $integration = TextField::new('integration');
        $optionsYaml = Field::new('optionsYaml');
        $enabled = Field::new('enabled');
        $id = Field::new('id', 'ID');
        $config = TextField::new('config');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $that = TextareaField::new('this')->setTemplatePath('admin/integration_config_info.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$enabled, $title, $integration, $workspace, $createdAt, $that, $updatedAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $integration, $enabled, $config, $createdAt, $updatedAt, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled];
        }
    }
}
