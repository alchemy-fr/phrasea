<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Admin\Field\IntegrationChoiceField;
use App\Entity\Integration\WorkspaceIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class IntegrationCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly IntegrationChoiceField $integrationChoiceField)
    {
    }

    public static function getEntityFqcn(): string
    {
        return WorkspaceIntegration::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(EntityFilter::new('workspace'))
            ->add($this->integrationChoiceField->createFilter('integration'))
            ->add(BooleanFilter::new('enabled'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'title', 'integration', 'config']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield BooleanField::new('enabled');
        yield TextField::new('title');
        yield AssociationField::new('workspace');
        yield AssociationField::new('needs');
        yield TextField::new('if')
            ->setHelp('Based on Symfony Expression Language.
<br/>e.g.
<br/>asset.getSource().getType() matches \'#^image/#\'
<br/>or
<br/>asset.getCreatedAt() > date(\'2000-01-01\')
')
        ->hideOnIndex();
        yield $this->integrationChoiceField->create('integration');
        yield TextareaField::new('configYaml', 'Config')
            ->onlyOnForms();
        yield JsonField::new('config')
            ->onlyOnDetail();
        yield ArrayField::new('this', 'Config info')
            ->setTemplatePath('admin/integration_config_info.html.twig')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
