<?php

namespace Alchemy\ConfiguratorBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("'.SuperAdminVoter::ROLE.'") or is_granted("'.JwtUser::ROLE_TECH.'")'))]
class ConfiguratorEntryCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    )
    {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::new('push', 'Push Configuration', 'fa fa-upload')
            ->createAsGlobalAction()
            ->linkToCrudAction('configuratorPush')
        );
    }

    public function configuratorPush(AdminContext $context): Response
    {
        $this->bus->dispatch(new DeployConfig());
        $this->addFlash('success', 'Configuration Push scheduled');

        return $this->returnToReferer($context);
    }

    public static function getEntityFqcn(): string
    {
        return ConfiguratorEntry::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield TextareaField::new('value')
            ->hideOnIndex()
            ->setFormTypeOption('attr', [
                'rows' => 20,
                'style' => 'font-family: monospace;',
            ])
        ;
        yield DateTimeField::new('createdAt')
        ->hideOnForm();
        yield DateTimeField::new('updatedAt')
        ->hideOnForm();
    }
}
