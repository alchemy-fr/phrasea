<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Admin\Field\ObjectTypeChoiceField;
use Alchemy\TrackBundle\AlchemyTrackBundle;
use App\Entity\Log\ActionLog;
use App\Model\ActionLogTypeEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ActionLogCrudController extends AbstractAdminCrudController
{
    public function __construct(
        #[Autowire(service: AlchemyTrackBundle::OBJECT_MAPPING_SERVICE_ID)]
        private readonly ObjectMapping $objectMapping,
        private readonly ObjectTypeChoiceField $objectTypeChoiceField,
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
            ->add('userId')
            ->add('impersonatorId')
            ->add(ChoiceFilter::new('objectType')
                ->setChoices($objectTypeChoices))
            ->add('objectId')
            ->add(ChoiceFilter::new('action')
                ->setChoices(ActionLogTypeEnum::getChoices()))
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
        return ActionLog::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('date')->hideOnForm();
        yield ChoiceField::new('action')->setChoices(ActionLogTypeEnum::getChoices());
        yield CodeField::new('userId');
        yield CodeField::new('impersonatorId');
        yield $this->objectTypeChoiceField->create('objectType', 'Object Type');
        yield TextField::new('objectId', 'Object ID');
        yield JsonField::new('meta')->hideOnIndex();
        yield JsonField::new('data');
    }
}
