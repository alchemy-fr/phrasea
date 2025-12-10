<?php

namespace Alchemy\TrackBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\JsonField;
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

class ChangeLogCrudController extends AbstractAdminCrudController
{
    private const array ACTION_CHOICES = [
        'Update' => TrackActionTypeEnum::UPDATE,
        'Create' => TrackActionTypeEnum::CREATE,
        'Delete' => TrackActionTypeEnum::DELETE,
    ];

    public function __construct(
        private ObjectMapping $objectMapping,
        private ObjectTypeChoiceField $objectTypeChoiceField,
    ) {
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
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
            ->add('userId')
            ->add('impersonatorId')
            ->add(ChoiceFilter::new('objectType')
                ->setChoices($objectTypeChoices))
            ->add('objectId')
            ->add(ChoiceFilter::new('action')
            ->setChoices(self::ACTION_CHOICES))
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
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
        yield ChoiceField::new('action')->setChoices(self::ACTION_CHOICES);
        yield CodeField::new('userId');
        yield CodeField::new('impersonatorId');
        yield $this->objectTypeChoiceField->create('objectType', 'Object Type');
        yield TextField::new('objectId', 'Object ID');
        yield JsonField::new('meta')->hideOnIndex();
        yield JsonField::new('changes');
    }
}
