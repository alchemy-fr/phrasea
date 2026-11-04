<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Automation\AutomationRun;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationTriggerEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AutomationRunCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AutomationRun::class;
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
            ->setEntityLabelInSingular('Automation Run')
            ->setEntityLabelInPlural('Automation Runs')
            ->setSearchFields(['id'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('automation'))
            ->add(BooleanFilter::new('dryRun'))
            ->add(DateTimeFilter::new('startedAt'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('automation');
        yield ChoiceField::new('trigger')
            ->setChoices(AutomationTriggerEnum::getChoices());
        yield BooleanField::new('dryRun')
            ->renderAsSwitch(false);
        yield ChoiceField::new('status')
            ->setChoices(AutomationRunStatusEnum::getChoices());
        yield $this->userChoiceField->create('initiatorId', 'Initiator')
            ->hideOnIndex();
        yield IntegerField::new('matchedCount', 'Matched');
        yield IntegerField::new('newCount', 'New');
        yield IntegerField::new('processedCount', 'Processed');
        yield IntegerField::new('failedCount', 'Failed');
        yield JsonField::new('actionResults')
            ->hideOnIndex();
        yield TextareaField::new('error')
            ->hideOnIndex();
        yield DateTimeField::new('startedAt');
        yield DateTimeField::new('endedAt');
        yield DateTimeField::new('createdAt')
            ->hideOnIndex();
    }
}
