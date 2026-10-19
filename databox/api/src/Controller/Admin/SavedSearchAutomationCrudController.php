<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Automation\SavedSearchAutomation;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationScopeEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * Read only: automations act as the owner of their saved search and are managed from the client.
 */
class SavedSearchAutomationCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SavedSearchAutomation::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Saved Search Automation')
            ->setEntityLabelInPlural('Saved Search Automations')
            ->setSearchFields(['id', 'name'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(EntityFilter::new('savedSearch'))
            ->add(BooleanFilter::new('enabled'))
            ->add(DateTimeFilter::new('nextRunAt'))
            ->add(DateTimeFilter::new('lastRunAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield AssociationField::new('savedSearch');
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield TextField::new('schedule');
        yield TextField::new('timezone')
            ->hideOnIndex();
        yield ChoiceField::new('scope')
            ->setChoices(AutomationScopeEnum::getChoices());
        yield JsonField::new('actions')
            ->hideOnIndex();
        yield DateTimeField::new('nextRunAt');
        yield DateTimeField::new('lastRunAt');
        yield ChoiceField::new('lastRunStatus')
            ->setChoices(AutomationRunStatusEnum::getChoices());
        yield IntegerField::new('consecutiveFailures')
            ->hideOnIndex();
        yield $this->userChoiceField->create('createdBy', 'Created by')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnIndex();
        yield DateTimeField::new('updatedAt')
            ->hideOnIndex();
    }
}
