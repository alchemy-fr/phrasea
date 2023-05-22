<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
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
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewWorkflow = Action::new('triggerIngest', 'Trigger Ingest', 'fa fa-gear')
            ->setHtmlAttributes(['target' => '_blank'])
            ->linkToCrudAction('triggerIngest');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewWorkflow)
        ;
    }

    public function triggerIngest(AdminContext $context): Response
    {
        /** @var Asset $asset */
        $asset = $context->getEntity()->getInstance();

        $this->workflowOrchestrator->dispatchEvent(new WorkflowEvent('asset_ingest', [
            'assetId' => $asset->getId(),
            'workspaceId' => $asset->getWorkspaceId(),
        ]));

        return $this->redirect($context->getReferrer());
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Asset')
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy'])
            ->setPaginatorPageSize(200);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'));
    }

    public function configureFields(string $pageName): iterable
    {
        $title = TextField::new('title');
        $workspace = AssociationField::new('workspace');
        $tags = AssociationField::new('tags');
        $privacy = PrivacyField::new('privacy');
        $ownerUser = $this->userChoiceField->create('ownerId', 'Owner');
        $id = IdField::new();
        $key = TextField::new('key');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $locale = TextField::new('locale');
        $collections = AssociationField::new('collections');
        $storyCollection = AssociationField::new('storyCollection');
        $referenceCollection = AssociationField::new('referenceCollection');
        $attributes = AssociationField::new('attributes');
        $file = Field::new('file');
        $source = AssociationField::new('source');
        $renditions = AssociationField::new('renditions');
        $collectionsCount = IntegerField::new('collections.count', '# Colls');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $title, $workspace, $privacy, $collectionsCount, $source, $key, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $title, $ownerUser, $key, $createdAt, $updatedAt, $locale, $privacy, $collections, $tags, $storyCollection, $referenceCollection, $attributes, $file, $renditions, $workspace];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$title, $workspace, $tags, $privacy, $ownerUser];
        }

        return [];
    }
}
