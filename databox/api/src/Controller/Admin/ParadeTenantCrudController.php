<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Entity\Parade\ParadeTenant;
use App\Parade\ParadeProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ParadeTenantCrudController extends AbstractAdminCrudController
{
    use ParadeMembersTrait;

    public function __construct(
        private readonly ParadeProvisioner $provisioner,
        private readonly KeycloakClient $keycloakClient,
        private readonly ServiceAccountClient $serviceAccountClient,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ParadeTenant::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Tenant')
            ->setEntityLabelInPlural('Tenants')
            ->setSearchFields(['slug', 'name'])
            ->setHelp(Crud::PAGE_INDEX, 'A tenant is a media library of Parade, declared by Parade when its media libraries are published: slug and name come from Parade. A tenant no longer declared is marked as removed from Parade. Its sub-roles and members are managed from the Parade administration: Phrasea creates the Keycloak groups under /parade/<slug> (admins, techniques, fonctionnels), marked as managed by Parade, and applies the memberships. Keycloak groups are never deleted.');
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $members = Action::new('members', 'Members', 'fas fa-users')
            ->linkToCrudAction('members')
            ->displayIf(fn (ParadeTenant $tenant): bool => [] !== $tenant->getGroups());

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action->displayIf(fn (ParadeTenant $tenant): bool => $tenant->isRemoved()))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn (Action $action) => $action->displayIf(fn (ParadeTenant $tenant): bool => $tenant->isRemoved()))
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $members)
            ->add(Crud::PAGE_DETAIL, $members);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('slug')
            ->setDisabled()
            ->setHelp('Slug of the media library in Parade.');
        yield TextField::new('name')
            ->setDisabled();
        yield DateTimeField::new('removedAt', 'Removed from Parade')
            ->hideOnForm();
        yield BooleanField::new('subRoles', 'Sub-roles')
            ->renderAsSwitch(false);
        yield JsonField::new('groups', 'Keycloak groups')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        throw new AccessDeniedHttpException('Tenants are declared by Parade.');
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        throw new AccessDeniedHttpException('Tenants are declared by Parade.');
    }

    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        assert($entityInstance instanceof ParadeTenant);
        if (!$entityInstance->isRemoved()) {
            throw new AccessDeniedHttpException('Only a tenant removed from Parade can be deleted.');
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[AdminRoute(path: '{entityId}/members', options: ['methods' => ['GET', 'POST']])]
    public function members(AdminContext $context): Response
    {
        $tenant = $context->getEntity()->getInstance();
        assert($tenant instanceof ParadeTenant);
        $slug = $tenant->getSlug();

        return $this->handleMembers(
            $context,
            $this->provisioner,
            $this->keycloakClient,
            $this->serviceAccountClient,
            sprintf('%s - Members', $tenant->getName()),
            $tenant->getGroups(),
            [
                'admin' => sprintf('Administrators (/parade/%s/admins)', $slug),
                'technical' => sprintf('Technical (/parade/%s/techniques)', $slug),
                'functional' => sprintf('Functional (/parade/%s/fonctionnels)', $slug),
            ],
            true,
        );
    }
}
