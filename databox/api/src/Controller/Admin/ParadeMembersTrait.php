<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Parade\ParadeProvisioner;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;

trait ParadeMembersTrait
{
    private const string MEMBERS_CSRF_ID = 'parade_members';

    private function handleMembers(
        AdminContext $context,
        ParadeProvisioner $provisioner,
        KeycloakClient $keycloakClient,
        ServiceAccountClient $serviceAccountClient,
        string $title,
        array $groups,
        array $labels,
        bool $readOnly = false,
    ): Response {
        $request = $context->getRequest();
        if ($request->isMethod('POST') && !$readOnly) {
            $groupId = (string) $request->request->get('groupId');
            $userId = (string) $request->request->get('userId');
            if (!$this->isCsrfTokenValid(self::MEMBERS_CSRF_ID, (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid form, please retry.');
            } elseif (!in_array($groupId, $groups, true) || '' === $userId) {
                $this->addFlash('danger', 'Unknown group or user.');
            } else {
                try {
                    if ('remove' === $request->request->get('operation')) {
                        $provisioner->removeMember($groupId, $userId);
                        $this->addFlash('success', 'Member removed.');
                    } else {
                        $provisioner->addMember($groupId, $userId);
                        $this->addFlash('success', 'Member added.');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('danger', sprintf('Keycloak refused the change: %s', $e->getMessage()));
                }
            }

            return $this->redirect($request->getUri());
        }

        $sections = [];
        foreach ($labels as $role => $label) {
            $groupId = $groups[$role] ?? null;
            $sections[] = [
                'label' => $label,
                'groupId' => $groupId,
                'members' => null !== $groupId ? $provisioner->listMembers($groupId) : [],
            ];
        }

        $users = $readOnly ? [] : $serviceAccountClient->executeWithAccessToken(
            fn (string $accessToken): array => $keycloakClient->getUsers($accessToken, ['limit' => 500])
        );
        usort($users, fn (array $a, array $b): int => strcmp($a['username'] ?? '', $b['username'] ?? ''));

        return $this->render('admin/parade_members.html.twig', [
            'title' => $title,
            'sections' => $sections,
            'users' => $users,
            'csrfId' => self::MEMBERS_CSRF_ID,
            'readOnly' => $readOnly,
        ]);
    }
}
