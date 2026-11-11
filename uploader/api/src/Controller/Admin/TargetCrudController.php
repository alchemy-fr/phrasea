<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\GroupChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

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
            ->setSearchFields(['id', 'slug', 'name', 'description', 'targetUrl', 'defaultDestination', 'authorizationKey', 'authorizationScheme', 'allowedGroups']);
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
            ->setHelp('Leave empty for pull mode. i.e: "https://phraseanet.phrasea.local/api/v1/upload/enqueue/" for Phraseanet');
        yield TextField::new('authorizationScheme')
            ->setHelp('Use "OAuth" for Phraseanet')
            ->setFormTypeOptions(['attr' => ['placeholder' => 'Defaults to "'.AssetConsumerNotifyHandler::DEFAULT_AUTHORIZATION_SCHEME.'"']])
            ->onlyOnForms();
        yield TextField::new('authorizationKey')
            ->hideOnIndex()
        ;
        yield TextField::new('defaultDestination')
            ->setHelp('i.e: "42" (for Phraseanet collection), "cdc3679f-3f37-4260-8de7-b649ecc8c1cc" (for Databox collection)')
            ->hideOnIndex();
        yield GroupChoiceField::new('allowedGroups')
            ->hideOnIndex();
        yield BooleanField::new('enabled');
        yield BooleanField::new('hidden')
            ->setHelp('Hide this target from the list of available targets');
        yield BooleanField::new('hasForm', 'Form')
            ->renderAsSwitch(false)
            ->onlyOnIndex();
        yield BooleanField::new('hasData', 'Data')
            ->renderAsSwitch(false)
            ->onlyOnIndex();
        yield JsonField::new('form')
            ->setHelp('The upload form, designed with the form builder of the Uploader client. "null" for no form.')
            ->hideOnIndex();
        yield JsonField::new('data')
            ->setHelp('Merged into the form data of every upload, over the values entered by the user. "null" for no data.')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
