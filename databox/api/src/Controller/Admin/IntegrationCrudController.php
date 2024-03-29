<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Admin\Field\IntegrationChoiceField;
use App\Entity\Integration\WorkspaceIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class IntegrationCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly IntegrationChoiceField $integrationChoiceField)
    {
    }

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
        $needs = AssociationField::new('needs');
        $if = TextField::new('if')
            ->setHelp('Based on Symfony Expression Language.
<br/>e.g.
<br/>asset.getSource().getType() matches \'#^image/#\'
<br/>or
<br/>asset.getCreatedAt() > date(\'2000-01-01\')
');
        $integration = $this->integrationChoiceField->create('integration');
        $optionsYaml = TextareaField::new('optionsYaml');
        $enabled = Field::new('enabled');
        $id = IdField::new();
        $config = JsonField::new('config');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $that = ArrayField::new('this', 'Config info')->setTemplatePath('admin/integration_config_info.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$enabled, $title, $integration, $workspace, $createdAt, $that, $updatedAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $integration, $enabled, $config, $createdAt, $updatedAt, $workspace, $needs];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled, $needs, $if];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $integration, $optionsYaml, $enabled, $needs, $if];
        }

        return [];
    }
}
