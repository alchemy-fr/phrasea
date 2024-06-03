<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Integration\IntegrationBasketData;
use App\Entity\Integration\IntegrationToken;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class IntegrationBasketDataCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return IntegrationBasketData::class;
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


    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('integration');
        yield AssociationField::new('object');
        yield CodeField::new('userId', 'User ID');
        yield CodeField::new('value');
        yield DateTimeField::new('createdAt');
    }
}
