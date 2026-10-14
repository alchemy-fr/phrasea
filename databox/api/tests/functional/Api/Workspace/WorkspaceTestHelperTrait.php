<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

trait WorkspaceTestHelperTrait
{
    /**
     * A user who only belongs to a Keycloak group (not known by the Keycloak mock).
     */
    private const string GROUPED_USER_UID = '77777777-fc1a-492b-9e76-c2e8e6979786';
    private const string GROUP_ID = 'group-of-readers';

    private static function authHeaders(?string $userId): array
    {
        if (null === $userId) {
            return [];
        }

        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    /**
     * The Keycloak mock users have no group: forge a token carrying a "groups" claim,
     * signed with the same test key.
     */
    private static function groupedUserHeaders(array $groups = [self::GROUP_ID]): array
    {
        $dir = dirname((string) new \ReflectionClass(KeycloakClientTestMock::class)->getFileName());
        $privateKey = InMemory::file($dir.'/key.pem');
        $configuration = Configuration::forAsymmetricSigner(new Sha256(), $privateKey, InMemory::file($dir.'/key.pub'));

        $now = new \DateTimeImmutable();
        $token = $configuration
            ->builder()
            ->issuedBy(getenv('KEYCLOAK_URL').'/realms/phrasea')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now->modify('+1 minute'))
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo(self::GROUPED_USER_UID)
            ->withClaim('azp', 'test')
            ->withClaim('preferred_username', 'grouped_user')
            ->withClaim('roles', ['databox'])
            ->withClaim('groups', $groups)
            ->withClaim('resource_access', [])
            ->getToken(new Sha256(), $privateKey);

        return [
            'Authorization' => 'Bearer '.$token->toString(),
        ];
    }

    /**
     * Creates a workspace the way the admin does (default policies, renditions, integrations).
     */
    private function createWs(string $slug, array $options = []): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName($options['name'] ?? 'Workspace '.$slug);
        $workspace->setSlug($slug);
        $workspace->setOwnerId($options['ownerId'] ?? 'custom_owner');
        $workspace->setPublic($options['public'] ?? false);
        if (isset($options['enabledLocales'])) {
            $workspace->setEnabledLocales($options['enabledLocales']);
        }
        if (isset($options['translations'])) {
            $workspace->setTranslations($options['translations']);
        }

        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
        self::getEntityManager()->flush();

        return $workspace;
    }

    private function grantUser(string $userId, Workspace $workspace, int $permissions = PermissionInterface::VIEW): void
    {
        self::getPermissionManager()->grantUserOnObject($userId, $workspace, $permissions);
    }

    private function grantGroup(string $groupId, Workspace $workspace, int $permissions = PermissionInterface::VIEW): void
    {
        self::getPermissionManager()->grantGroupOnObject($groupId, $workspace, $permissions);
    }

    private static function iri(Workspace|string $workspace): string
    {
        return '/workspaces/'.($workspace instanceof Workspace ? $workspace->getId() : $workspace);
    }

    /**
     * @return string[] slugs of the listed workspaces, sorted
     */
    private static function listedSlugs(array $data): array
    {
        $slugs = array_map(static fn (array $w): string => $w['slug'], $data['member']);
        sort($slugs);

        return $slugs;
    }
}
