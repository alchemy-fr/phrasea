<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Core\Workspace;
use App\Entity\Template\WorkspaceTemplate;
use App\Repository\Template\WorkspaceTemplateRepository;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly WorkspaceTemplateRepository $workspaceTemplateRepository,
        private readonly WorkspaceTemplater $workspaceTemplater,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Workspace::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workspace')
            ->setEntityLabelInPlural('Workspaces')
            ->setSearchFields(['id', 'name', 'slug', 'ownerId', 'config', 'enabledLocales', 'localeFallbacks'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $action = Action::new('saveAsTemplape', 'Save as template', 'fa fa-gear')
            ->linkToCrudAction('saveAsTemplate');

        return parent::configureActions($actions)->add(
            Crud::PAGE_DETAIL,
            $action
        );
    }

    public function saveAsTemplate(AdminContext $context): Response
    {
        /** @var Workspace $workspace */
        $workspace = $context->getEntity()->getInstance();
        $wt = $this->workspaceTemplater->saveWorkspaceAsTemplate($workspace);

        $url = $this->adminUrlGenerator
            ->setController(WorkspaceTemplateCrudController::class)
            ->setAction(Crud::PAGE_EDIT)
            ->setEntityId($wt->getId())
            ->generateUrl();

        $this->addFlash('info', sprintf('Workspace template "%s" created.', $wt->getName()));

        return $this->redirect($url);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('public'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield TextField::new('slug');
        yield TextField::new('ownerId')
            ->onlyOndetail();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield ArrayField::new('enabledLocales');
        yield ArrayField::new('localeFallbacks');
        yield BooleanField::new('public')
            ->setHelp('If you need to expose a collection publicly, then its workspace has to be public.');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('deletedAt')
            ->onlyOnDetail();
        yield AssociationField::new('collections')
            ->onlyOnDetail();
        yield AssociationField::new('tags')
            ->onlyOnDetail();
        yield AssociationField::new('renditionPolicies')
            ->onlyOnDetail();
        yield AssociationField::new('renditionDefinitions')
            ->onlyOnDetail();
        yield ChoiceField::new('applyWorkspaceTemplate', null)
            ->setFormTypeOption('mapped', false)
            ->setChoices($this->getTemplateChoice());
        yield AssociationField::new('attributeDefinitions')
            ->onlyOnDetail();
        yield AssociationField::new('files')
        ->onlyOnDetail();
    }

    private function getTemplateChoice()
    {
        $templateChoices = [];
        foreach ($this->workspaceTemplateRepository->findAll() as $template) {
            $templateChoices[$template->getName()] = $template->getId();
        }

        return $templateChoices;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        $this->applyWorkspaceTemplate($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $this->applyWorkspaceTemplate($entityInstance);
    }

    private function applyWorkspaceTemplate(Workspace $workspace)
    {
        $templateId = $this->getContext()->getRequest()->request->all('Workspace')['applyWorkspaceTemplate'];
        if ($templateId) {
            try {
                /** @var WorkspaceTemplate $t */
                $t = $this->workspaceTemplateRepository->findOneBy(['id' => $templateId]);
                if ($t && $t->getData()) {
                    $this->workspaceTemplater->importToWorkspace($workspace, $t->getData());
                }
            } catch (\Throwable $e) {
                $this->addFlash('warning', sprintf('Workspace template NOT applied because: %s.', $e->getMessage()));
            }
        }
    }
}
