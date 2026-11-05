<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Admin\Field\ObjectTypeChoiceField;
use Alchemy\TrackBundle\AlchemyTrackBundle;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuditLogCrudController extends AbstractAdminCrudController
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
            ->add(ChoiceFilter::new('actorType')
                ->setChoices(AuditActorTypeEnum::getChoices()))
            ->add('userId')
            ->add('impersonatorId')
            ->add('clientId')
            ->add(ChoiceFilter::new('objectType')
                ->setChoices($objectTypeChoices))
            ->add('objectId')
            // Exact match: a LIKE does not apply to a UUID column
            ->add(AssociationIdentifierFilter::new('workspaceId'))
            ->add(AssociationIdentifierFilter::new('correlationId'))
            ->add(ChoiceFilter::new('action')
                ->setChoices(AuditLogActionEnum::getChoices()))
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
        return AuditLog::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('date')->hideOnForm();
        yield ChoiceField::new('action')->setChoices(AuditLogActionEnum::getChoices());
        yield ChoiceField::new('actorType')->setChoices(AuditActorTypeEnum::getChoices());
        yield CodeField::new('userId');
        yield TextField::new('userName');
        yield CodeField::new('impersonatorId');
        yield CodeField::new('clientId');
        yield $this->objectTypeChoiceField->create('objectType', 'Object Type');
        yield TextField::new('objectId', 'Object ID');
        yield TextField::new('objectLabel', 'Object');
        yield CodeField::new('workspaceId')->hideOnIndex();
        yield CodeField::new('correlationId')->hideOnIndex();
        yield JsonField::new('meta')->hideOnIndex();
        yield JsonField::new('data');
    }
}
