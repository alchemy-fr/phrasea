<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AuthBundle\Client\GroupOwnershipException;
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
            ->setHelp(Crud::PAGE_INDEX, 'A tenant is a media library of Parade, declared by Parade when its media libraries are published: slug and name come from Parade. A tenant no longer declared is marked as removed from Parade. With sub-roles, its own Keycloak groups are created under /parade/<slug> (admins, techniques, fonctionnels), marked as managed by Parade; otherwise it is administered by the Parade groups. A group with the same path that is not marked is never reused unless you adopt it. Keycloak groups are never deleted.');
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $members = Action::new('members', 'Members', 'fas fa-users')
            ->linkToCrudAction('members')
            ->displayIf(fn (ParadeTenant $tenant): bool => [] !== $tenant->getGroups());

        return parent::configureActions($actions)
            ->disable(Action::NEW)
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
            ->renderAsSwitch(false)
            ->setHelp('Create the administrators, technical and functional groups of this tenant.');
        yield BooleanField::new('adoptExistingGroups', 'Adopt existing groups')
            ->onlyOnForms()
            ->setHelp('Reuse groups of this tenant that already exist but are not marked as managed by Parade, and mark them.');
        yield JsonField::new('groups', 'Keycloak groups')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        assert($entityInstance instanceof ParadeTenant);
        $this->provision($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        assert($entityInstance instanceof ParadeTenant);
        $this->provision($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
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
        );
    }

    private function provision(ParadeTenant $tenant): void
    {
        if (!$tenant->hasSubRoles()) {
            return;
        }

        try {
            $this->provisioner->provisionTenant($tenant, $tenant->isAdoptExistingGroups());
            $this->addFlash('success', sprintf('Keycloak groups of tenant "%s" are ready.', $tenant->getSlug()));
        } catch (GroupOwnershipException $e) {
            $tenant->setSubRoles(false);
            $this->addFlash('danger', sprintf('The groups of tenant "%s" were not created: %s', $tenant->getSlug(), $e->getMessage()));
        } catch (\Throwable $e) {
            $tenant->setSubRoles(false);
            $this->addFlash('danger', sprintf('The Keycloak groups of tenant "%s" were not created: %s', $tenant->getSlug(), $e->getMessage()));
        }
    }
}
