<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;

class AssetCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Asset::class;
    }

    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly WorkflowOrchestrator $workflowOrchestrator,
        private readonly PrivacyField $privacyField,
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewWorkflow = Action::new('triggerIngest', 'Trigger Ingest', 'fa fa-gear')
            ->linkToCrudAction('triggerIngest');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $viewWorkflow)
        ;
    }

    public function triggerIngest(AdminContext $context): Response
    {
        /** @var Asset $asset */
        $asset = $context->getEntity()->getInstance();

        $user = $context->getUser();
        if (!$user instanceof JwtUser) {
            throw new \InvalidArgumentException(sprintf('Invalid user: %s', get_debug_type($user)));
        }

        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()), [
                WorkflowState::INITIATOR_ID => $user->getId(),
            ]);

        return $this->returnToReferer($context);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Assets')
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('id'))
            ->add(TextFilter::new('title'))
            ->add(EntityFilter::new('workspace'))
            ->add(AssociationIdentifierFilter::new('referenceCollection'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('title')
            ->hideOnForm();
        yield AssociationField::new('workspace');
        yield AssociationField::new('storyCollection')
            ->hideOnForm();
        yield $this->userChoiceField->create('ownerId', 'Owner')
            ->hideOnIndex();
        yield $this->privacyField->create('privacy');
        yield IntegerField::new('collections.count', '# Colls')
            ->onlyOnIndex();
        yield AssociationField::new('source')
            ->onlyOnIndex();
        yield TextField::new('key')
            ->hideOnForm();
        yield TextField::new('externalId')
            ->hideOnForm();
        yield IdField::new('trackingId')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
        yield DateTimeField::new('deletedAt')
            ->onlyOnDetail();
        yield AssociationField::new('tags')
            ->hideOnIndex();
        yield TextField::new('locale')
            ->onlyOnDetail();
        yield AssociationField::new('collections')
            ->onlyOnDetail();
        yield AssociationField::new('referenceCollection')
            ->onlyOnDetail()
            ->autocomplete();
        yield AssociationField::new('attributes')
            ->autocomplete()
            ->onlyOnDetail();
        yield Field::new('file')
            ->onlyOnDetail();
        yield AssociationField::new('renditions')
            ->onlyOnDetail();
        yield AssociationField::new('fileVersions')
            ->onlyOnDetail();
        yield JsonField::new('notificationSettings')
            ->hideOnIndex();
        yield BooleanField::new('autoSubscribeOwner')
            ->hideOnIndex();
    }
}
