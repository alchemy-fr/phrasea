<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Consumer\Handler\AssetConsumerNotify;
use App\Entity\Commit;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Messenger\MessageBusInterface;

class CommitCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UserChoiceField $userChoiceField,
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
            ->setEntityLabelInPlural('Commit')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'totalSize', 'formData', 'options', 'userId', 'token', 'notify', 'locale']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('id'))
            ->add(TextFilter::new('userId'))
            ->add(BooleanFilter::new('acknowledged'))
            ->add(DateTimeFilter::new('acknowledgedAt'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('target');
        yield IdField::new('userId');
        yield $this->userChoiceField->create('userId', 'User');
        yield TextField::new('token')
            ->hideOnIndex();
        yield BooleanField::new('acknowledged')
            ->renderAsSwitch(false);
        yield BooleanField::new('notify')
            ->renderAsSwitch(false)
            ->hideOnIndex();
        yield IntegerField::new('totalSize')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig')
            ->hideOnIndex();
        yield JsonField::new('formData')
            ->hideOnIndex();
        yield JsonField::new('options')
            ->hideOnIndex();
        yield TextField::new('locale');
        yield TextField::new('formLocale')
            ->hideOnIndex();
        yield DateTimeField::new('acknowledgedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield AssociationField::new('assets')
            ->hideOnForm();
    }

    public function triggerAgain(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator)
    {
        $commit = $adminContext->getEntity()->getInstance();
        if (!$commit instanceof Commit) {
            throw new \LogicException('Entity is missing or not a Commit');
        }
        if ($commit->isAcknowledged()) {
            $this->addFlash('danger', 'Commit has been acknowledged');
        } else {
            $this->bus->dispatch(new AssetConsumerNotify($commit->getId()));
        }

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->setEntityId($commit->getId())
            ->generateUrl();

        return $this->redirect($targetUrl);
    }
}
