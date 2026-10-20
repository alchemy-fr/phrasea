<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Parade\ParadeLoginClient;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ParadeLoginClientCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParadeLoginClient::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Login page')
            ->setEntityLabelInPlural('Login pages')
            ->setDefaultSort(['page' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'Login pages declared by Parade: their branding is edited in Parade, which pushes it here when a login page is published and applied; Phrasea then creates or updates the matching Keycloak client. Read-only.');
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('page');
        yield TextField::new('clientId', 'Keycloak client');
        yield TextField::new('status');
        yield TextField::new('error')
            ->hideOnIndex();
        yield JsonField::new('attributes', 'Branding')
            ->hideOnIndex();
        yield DateTimeField::new('appliedAt');
    }
}
