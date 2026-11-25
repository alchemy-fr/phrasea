<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Consumer\CommitNotifier;
use App\Entity\Commit;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class CommitCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly CommitNotifier $commitNotifier,
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Commit::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $triggerAgainAction = Action::new('triggerAgain')
            ->linkToCrudAction('triggerAgain');

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $triggerAgainAction);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Commit')
            ->setEntityLabelInPlural('Commits')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'totalSize', 'userId', 'token', 'locale']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('id'))
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add(EntityFilter::new('target'))
            ->add(AssociationIdentifierFilter::new('uploadLink'))
            ->add(BooleanFilter::new('notify'))
            ->add(BooleanFilter::new('acknowledged'))
            ->add(DateTimeFilter::new('acknowledgedAt'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('target')
            ->autocomplete();
        yield $this->userChoiceField->create('userId', 'User');
        yield TextField::new('token')
            ->hideOnIndex();
        yield BooleanField::new('acknowledged')
            ->renderAsSwitch(false);
        yield BooleanField::new('notify')
            ->renderAsSwitch(false)
            ->hideOnIndex();
        yield IntegerField::new('totalSize')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        yield TextField::new('uploadLink.name', 'Upload link')
            ->hideOnForm();
        yield JsonField::new('formData')
            ->hideOnIndex();
        yield JsonField::new('options')
            ->hideOnIndex();
        yield TextField::new('locale')
            ->hideOnIndex();
        yield TextField::new('formLocale')
            ->hideOnIndex();
        yield DateTimeField::new('notifyDispatchedAt', 'Target notified at')
            ->hideOnIndex()
            ->hideOnForm();
        yield DateTimeField::new('acknowledgedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield AssociationField::new('assets')
            ->autocomplete()
            ->hideOnForm();
    }

    #[AdminRoute(path: '/{entityId}/trigger-again', name: 'trigger_again')]
    public function triggerAgain(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator)
    {
        $commit = $adminContext->getEntity()->getInstance();
        if (!$commit instanceof Commit) {
            throw new \LogicException('Entity is missing or not a Commit');
        }
        if ($commit->isAcknowledged()) {
            $this->addFlash('danger', 'Commit has been acknowledged');
        } else {
            $this->commitNotifier->notifyAgain($commit->getId());
        }

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->setEntityId($commit->getId())
            ->generateUrl();

        return $this->redirect($targetUrl);
    }
}
