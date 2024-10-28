<?php

namespace App\Controller\Admin;

use App\Entity\Target;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\GroupChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class TargetCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Target::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Target')
            ->setEntityLabelInPlural('Target')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'slug', 'name', 'description', 'targetUrl', 'defaultDestination', 'targetAccessToken', 'targetTokenType', 'allowedGroups']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('enabled'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('slug');
        yield TextField::new('name');  
        yield TextareaField::new('description')
            ->hideOnIndex();   
        yield CodeField::new('pullModeUrl', 'Pull mode URL')
            ->onlyOnIndex();
        yield TextField::new('targetUrl')
            ->setHelp('Leave empty for pull mode. i.e: "https://phraseanet.phrasea.local/api/v1/upload/enqueue/" for Phraseanet, "http://databox-api/incoming-uploads" for Databox upload');
        yield TextField::new('targetTokenType')
            ->setHelp('Use "OAuth" for Phraseanet')
            ->setFormTypeOptions(['attr' => ['placeholder' => 'Defaults to "Bearer"']])
            ->onlyOnForms();   
        yield TextField::new('targetAccessToken');
        yield TextField::new('defaultDestination')
            ->setHelp('i.e: "42" (for Phraseanet collection), "cdc3679f-3f37-4260-8de7-b649ecc8c1cc" (for Databox collection)')
            ->hideOnIndex();
        yield GroupChoiceField::new('allowedGroups')
            ->hideOnIndex();
        yield BooleanField::new('enabled');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();

    }
}
