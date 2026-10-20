<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AuthBundle\Client\GroupOwnershipException;
use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Entity\Parade\Parade;
use App\Parade\ParadeFixedClientsApplier;
use App\Parade\ParadeLegacyCleaner;
use App\Parade\ParadeProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Response;

class ParadeCrudController extends AbstractAdminCrudController
{
    use ParadeMembersTrait;

    public function __construct(
        private readonly ParadeProvisioner $provisioner,
        private readonly ParadeFixedClientsApplier $fixedClients,
        private readonly ParadeLegacyCleaner $legacyCleaner,
        private readonly EntityManagerInterface $em,
        private readonly KeycloakClient $keycloakClient,
        private readonly ServiceAccountClient $serviceAccountClient,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Parade::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Parade')
            ->setEntityLabelInPlural('Parade')
            ->setHelp(Crud::PAGE_INDEX, 'Enabling Parade creates its Keycloak groups under /parade (super-admins, admins, techniques, fonctionnels), marked as managed by Parade, and makes Parade available on the dashboard. A group with the same path that is not marked is never reused unless you adopt it. Nothing is ever deleted.');
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $hasParade = $this->em->getRepository(Parade::class)->count([]) > 0;
        $members = Action::new('members', 'Members', 'fas fa-users')
            ->linkToCrudAction('members')
            ->displayIf(fn (Parade $parade): bool => [] !== $parade->getGroups());

        $legacy = Action::new('legacyClean', 'Clean legacy Keycloak (temporary)', 'fas fa-broom')
            ->linkToCrudAction('legacyClean');

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $members)
            ->add(Crud::PAGE_DETAIL, $members)
            ->add(Crud::PAGE_INDEX, $legacy)
            ->add(Crud::PAGE_DETAIL, $legacy)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setLabel('Add Parade')
                ->displayIf(fn () => !$hasParade))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->disable(Action::DELETE);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield UrlField::new('url', 'URL')
            ->setHelp('Public URL of the Parade client.');
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield BooleanField::new('adoptExistingGroups', 'Adopt existing groups')
            ->onlyOnForms()
            ->setHelp('Reuse /parade groups that already exist but are not marked as managed by Parade, and mark them.');
        yield JsonField::new('groups', 'Keycloak groups')
            ->hideOnForm();
        yield JsonField::new('clientsReport', 'Keycloak clients')
            ->hideOnForm()
            ->hideOnIndex();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();

        yield FormField::addFieldset('Keycloak (temporary settings)')
            ->setHelp('Values currently fixed by convention, kept here as a trace and to adjust them if needed. Empty means the default value.');
        yield TextField::new('rootGroupName', 'Root group')
            ->onlyOnForms()
            ->setHelp('Default: parade');
        yield TextField::new('superAdminsGroupName', 'Super administrators group')
            ->onlyOnForms()
            ->setHelp('Default: super-admins');
        yield TextField::new('adminsGroupName', 'Administrators group')
            ->onlyOnForms()
            ->setHelp('Default: admins (site and tenants)');
        yield TextField::new('technicalGroupName', 'Technical group')
            ->onlyOnForms()
            ->setHelp('Default: techniques (site and tenants)');
        yield TextField::new('functionalGroupName', 'Functional group')
            ->onlyOnForms()
            ->setHelp('Default: fonctionnels (site and tenants)');
        yield TextField::new('ownerAttribute', 'Ownership attribute')
            ->onlyOnForms()
            ->setHelp('Default: phraseaApp');
        yield TextField::new('ownerValue', 'Ownership value')
            ->onlyOnForms()
            ->setHelp('Default: parade');
        yield TextField::new('mainClientId', 'Main Keycloak client')
            ->onlyOnForms()
            ->setHelp('Public client used by Parade to sign users in; base of the login page clients. Default: phrasea-front');
        yield TextField::new('serviceAccountClientId', 'Parade service account client')
            ->onlyOnForms()
            ->setHelp('Confidential client of the Parade server. Not created here (its secret stays on the Parade side): only its rights are applied. Default: parade-admin');
        yield TextField::new('serviceAccountRealmRole', 'Service account realm role')
            ->onlyOnForms()
            ->setHelp('Default: admin-scopes');
        yield TextField::new('serviceAccountScopes', 'Service account scopes')
            ->onlyOnForms()
            ->setHelp('Comma separated. Default: workspace:list, collection:list, rendition-definition:list, attribute-definition:list, groups');
        yield TextField::new('serviceAccountClientRoles', 'Service account realm-management roles')
            ->onlyOnForms()
            ->setHelp('Comma separated. Default: view-users (notification directory)');
        yield TextField::new('orderClientId', 'Order exchange client')
            ->onlyOnForms()
            ->setHelp('Not applied yet (trace). Default: parade-to-order');
        yield TextField::new('loanClientId', 'Loan exchange client')
            ->onlyOnForms()
            ->setHelp('Not applied yet (trace). Default: parade-to-loan');
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        assert($entityInstance instanceof Parade);
        $this->provision($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->provisioner->publishState($entityInstance);
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        assert($entityInstance instanceof Parade);
        $this->provision($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
        $this->provisioner->publishState($entityInstance);
    }

    #[AdminRoute(path: '{entityId}/members', options: ['methods' => ['GET', 'POST']])]
    public function members(AdminContext $context): Response
    {
        $parade = $context->getEntity()->getInstance();
        assert($parade instanceof Parade);

        return $this->handleMembers(
            $context,
            $this->provisioner,
            $this->keycloakClient,
            $this->serviceAccountClient,
            sprintf('%s - Members', $parade->getName()),
            $parade->getGroups(),
            [
                'superAdmin' => 'Super administrators (/parade/super-admins)',
                'admin' => 'Administrators (/parade/admins)',
                'technical' => 'Technical (/parade/techniques)',
                'functional' => 'Functional (/parade/fonctionnels)',
            ],
        );
    }

    #[AdminRoute(path: '{entityId}/legacy-clean', options: ['methods' => ['GET', 'POST']])]
    public function legacyClean(AdminContext $context): Response
    {
        $parade = $context->getEntity()->getInstance();
        assert($parade instanceof Parade);
        $request = $context->getRequest();
        $report = null;
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parade_legacy_clean', (string) $request->request->get('_token'))) {
                $error = 'Invalid form, please retry.';
            } elseif ('yes' !== $request->request->get('confirm')) {
                $error = 'Tick the confirmation box to clean.';
            } else {
                $report = $this->legacyCleaner->clean($parade);
            }
        }

        try {
            $inventory = $this->legacyCleaner->inventory($parade);
        } catch (\Throwable $e) {
            $inventory = null;
            $error = sprintf('Keycloak could not be read: %s', $e->getMessage());
        }

        return $this->render('admin/parade_legacy_clean.html.twig', [
            'parade' => $parade,
            'inventory' => $inventory,
            'report' => $report,
            'error' => $error,
        ]);
    }

    private function provision(Parade $parade): void
    {
        if (!$parade->isEnabled()) {
            return;
        }

        try {
            $this->provisioner->provisionParade($parade, $parade->isAdoptExistingGroups());
            $this->addFlash('success', 'Parade Keycloak groups are ready.');
            $report = $this->fixedClients->apply($parade);
            $parade->setClientsReport([...$report, 'appliedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)]);
            foreach (['mainClient', 'serviceAccount'] as $key) {
                $entry = $report[$key];
                $this->addFlash(
                    in_array($entry['status'], ['created', 'conformant'], true) ? 'success' : 'warning',
                    sprintf('Keycloak client %s: %s%s', $entry['clientId'] ?? $key, $entry['status'], isset($entry['error']) ? ' ('.$entry['error'].')' : (!empty($entry['missing']) ? ' (missing: '.implode(', ', $entry['missing']).')' : '')),
                );
            }
        } catch (GroupOwnershipException $e) {
            $parade->setEnabled(false);
            $this->addFlash('danger', sprintf('Parade was not enabled: %s', $e->getMessage()));
        } catch (\Throwable $e) {
            $parade->setEnabled(false);
            $this->addFlash('danger', sprintf('Parade could not be enabled, its Keycloak groups were not created: %s', $e->getMessage()));
        }
    }
}
