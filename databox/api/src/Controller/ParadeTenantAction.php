<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Parade\ParadeTenant;
use App\Parade\ParadeProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ParadeTenantAction extends AbstractController
{
    private const string GROUPS_SCOPE_ROLE = 'ROLE_GROUPS';
    private const int MAX_TENANTS = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParadeProvisioner $provisioner,
    ) {
    }

    #[Route(path: '/parade-tenants/{slug}/sub-roles', requirements: ['slug' => '[a-z0-9][a-z0-9-]{0,62}'], methods: ['PUT'])]
    public function subRoles(string $slug, Request $request): JsonResponse
    {
        $this->authorize();
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body');
        }
        $enabled = is_array($data) ? ($data['enabled'] ?? null) : null;
        $adopt = is_array($data) ? ($data['adoptExistingGroups'] ?? false) : false;
        if (!is_bool($enabled) || !is_bool($adopt)) {
            throw new BadRequestHttpException('Invalid enabled flag');
        }
        $tenant = $this->em->getRepository(ParadeTenant::class)->findOneBy(['slug' => $slug, 'removedAt' => null]);
        if (null === $tenant) {
            throw new NotFoundHttpException(sprintf('Tenant "%s" is not declared by Parade.', $slug));
        }

        if ($enabled && ([] === $tenant->getGroups() || !$tenant->hasSubRoles())) {
            try {
                $this->provisioner->provisionTenant($tenant, $adopt);
            } catch (\Throwable $e) {
                return new JsonResponse(['slug' => $slug, 'subRoles' => $tenant->hasSubRoles(), 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
            }
        }
        $tenant->setSubRoles($enabled);
        $this->em->flush();

        return new JsonResponse(['slug' => $slug, 'subRoles' => $tenant->hasSubRoles(), 'groups' => (object) $tenant->getGroups()]);
    }

    #[Route(path: '/parade-tenants', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize();

        try {
            $data = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body');
        }
        $declared = is_array($data) ? ($data['tenants'] ?? null) : null;
        if (!is_array($declared) || !array_is_list($declared) || count($declared) > self::MAX_TENANTS) {
            throw new BadRequestHttpException('Invalid tenants');
        }

        $names = [];
        $rejected = [];
        foreach ($declared as $tenant) {
            $slug = is_array($tenant) ? ($tenant['slug'] ?? null) : null;
            $name = is_array($tenant) ? ($tenant['name'] ?? null) : null;
            if (!is_string($slug) || 1 !== preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $slug)) {
                throw new BadRequestHttpException(sprintf('Invalid tenant slug "%s"', is_string($slug) ? $slug : ''));
            }
            if (in_array($slug, ParadeProvisioner::RESERVED_TENANT_SLUGS, true)) {
                $rejected[] = $slug;
                continue;
            }
            if (!is_string($name) || '' === trim($name) || mb_strlen($name) > 100) {
                throw new BadRequestHttpException(sprintf('Invalid name of tenant "%s"', $slug));
            }
            $names[$slug] = trim($name);
        }

        $now = new \DateTimeImmutable();
        $existing = [];
        foreach ($this->em->getRepository(ParadeTenant::class)->findAll() as $tenant) {
            $existing[$tenant->getSlug()] = $tenant;
            if (isset($names[$tenant->getSlug()])) {
                $tenant->setName($names[$tenant->getSlug()]);
                $tenant->setRemovedAt(null);
            } elseif (!$tenant->isRemoved()) {
                $tenant->setRemovedAt($now);
            }
        }
        foreach ($names as $slug => $name) {
            if (!isset($existing[$slug])) {
                $tenant = new ParadeTenant();
                $tenant->setSlug($slug);
                $tenant->setName($name);
                $this->em->persist($tenant);
            }
        }
        $this->em->flush();

        return new JsonResponse([
            'declared' => array_keys($names),
            'rejected' => $rejected,
            'removed' => array_values(array_map(
                fn (ParadeTenant $tenant): string => (string) $tenant->getSlug(),
                array_filter($existing, fn (ParadeTenant $tenant): bool => $tenant->isRemoved()),
            )),
        ]);
    }

    private function authorize(): void
    {
        $user = $this->getUser();
        if ($user instanceof JwtOauthClient) {
            $this->denyAccessUnlessGranted(self::GROUPS_SCOPE_ROLE);
        } elseif ($user instanceof JwtUser) {
            $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
        } else {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }
    }
}
