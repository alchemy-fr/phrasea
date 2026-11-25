<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Integration\IntegrationToken;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\Response;

class IntegrationTokenCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return IntegrationToken::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'integration']);
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    /**
     * A token comes from the OAuth flow of its integration: it can be deleted (revoked), not
     * written. Removing the actions only hides them, since the AdminVoter grants everything to
     * the admins.
     */
    #[\Override]
    public function new(AdminContext $context): KeyValueStore|Response
    {
        throw new ForbiddenActionException($context);
    }

    #[\Override]
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        throw new ForbiddenActionException($context);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('integration'))
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add(DateTimeFilter::new('expiresAt'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('integration')
            ->autocomplete();
        yield $this->userChoiceField->create('userId', 'User');
        yield DateTimeField::new('expiresAt');
        yield BooleanField::new('expired')
            ->renderAsSwitch(false)
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
