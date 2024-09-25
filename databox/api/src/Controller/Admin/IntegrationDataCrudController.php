<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\CodeField;
use App\Entity\Integration\IntegrationData;
use Alchemy\AdminBundle\Field\UserChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class IntegrationDataCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private UserChoiceField $userChoiceField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return IntegrationData::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'integration']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('integration'))
            ->add(TextFilter::new('name'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('integration');
        yield TextField::new('name');
        yield TextField::new('objectType');
        yield IdField::new('objectId');
        yield $this->userChoiceField->create('userId', 'User');
        yield TextField::new('keyId');
        yield CodeField::new('value');
        yield DateTimeField::new('createdAt');
    }
}
