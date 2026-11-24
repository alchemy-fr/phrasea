<?php

namespace Alchemy\TrackBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Admin\Field\ObjectTypeChoiceField;
use Alchemy\TrackBundle\Entity\ChangeLog;
use Alchemy\TrackBundle\Model\TrackActionTypeEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class ChangeLogCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly ObjectTypeChoiceField $objectTypeChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        $objectTypeChoices = [];
        foreach ($this->objectMapping->getObjectTypes() as $objectType) {
            $objectTypeChoices[$this->objectMapping->getClassName($objectType)] = $objectType;
        }

        return $filters
            ->add(DateTimeFilter::new('date'))
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add(ChoiceFilter::new('objectType')
                ->setChoices($objectTypeChoices))
            ->add(AssociationIdentifierFilter::new('objectId', 'Object ID'))
            ->add(ChoiceFilter::new('action')
            ->setChoices(TrackActionTypeEnum::getChoices()))
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['date' => 'DESC'])
        ;
    }

    public static function getEntityFqcn(): string
    {
        return ChangeLog::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('date')->hideOnForm();
        yield ChoiceField::new('action')->setChoices(TrackActionTypeEnum::getChoices());
        yield CodeField::new('userId');
        yield $this->objectTypeChoiceField->create('objectType', 'Object Type');
        yield TextField::new('objectId', 'Object ID');
        yield JsonField::new('meta')->hideOnIndex();
        yield JsonField::new('changes')->hideOnIndex();
    }
}
