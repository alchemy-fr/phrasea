<?php

declare(strict_types=1);

namespace App\Tests\Functional\UploadLink;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Target;
use App\Entity\UploadLink;
use App\Security\Acl\UploadLinkAclObject;
use App\Security\UploadLinkUser;
use App\Tests\Functional\AbstractUploaderTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractUploadLinkTestCase extends AbstractUploaderTestCase
{
    protected const string FIXTURE = __DIR__.'/../../fixtures/32x32.jpg';
    protected const int FIXTURE_SIZE = 846;

    /**
     * Holds the `upload_link` permission: the creators of the links made by createLink() are in it.
     */
    protected const string CREATOR_GROUP = 'upload-link-creators';

    protected function createTarget(string $slug = 'link-target'): Target
    {
        $em = self::getEntityManager();

        $target = new Target();
        $target->setName('Target '.$slug);
        $target->setSlug($slug);
        // No target URL: the commits are not pushed to a consumer
        $target->setTargetUrl(null);
        $em->persist($target);
        $em->flush();

        return $target;
    }

    /**
     * By default, the creator got the permission to create it from a group (CREATOR_GROUP) they were in.
     *
     * @param array{enabled?: bool, expiresAt?: \DateTimeImmutable, maxAssets?: ?int, maxTotalSize?: ?int, name?: ?string, creatorGroups?: string[], creatorRoles?: string[]} $options
     */
    protected function createLink(Target $target, string $ownerId = KeycloakClientTestMock::USER_UID, array $options = []): UploadLink
    {
        $this->grantUploadLinkToGroup(self::CREATOR_GROUP);
        $em = self::getEntityManager();

        $link = new UploadLink();
        // A request made since may have detached it
        $link->setTarget($em->contains($target) ? $target : $em->find(Target::class, $target->getId()));
        $link->setOwnerId($ownerId);
        $link->setCreatorGroups($options['creatorGroups'] ?? [self::CREATOR_GROUP]);
        $link->setCreatorRoles($options['creatorRoles'] ?? []);
        $link->setName($options['name'] ?? 'Photographer');
        $link->setEnabled($options['enabled'] ?? true);
        $link->setExpiresAt($options['expiresAt'] ?? new \DateTimeImmutable('+30 days'));
        $link->setMaxAssets($options['maxAssets'] ?? null);
        $link->setMaxTotalSize($options['maxTotalSize'] ?? null);

        $em->persist($link);
        $em->flush();

        return $link;
    }

    protected function reloadLink(UploadLink $link): UploadLink
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(UploadLink::class, $link->getId());
    }

    protected function grantUploadLink(string $userId): void
    {
        self::getContainer()->get(PermissionManager::class)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            $userId,
            UploadLinkAclObject::OBJECT_KEY,
            null,
            PermissionInterface::EDIT,
        );
    }

    protected function grantUploadLinkToGroup(string $groupId): void
    {
        self::getContainer()->get(PermissionManager::class)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_GROUP_VALUE,
            $groupId,
            UploadLinkAclObject::OBJECT_KEY,
            null,
            PermissionInterface::EDIT,
        );
    }

    /**
     * @param string|null $session the browser session of the holder of the link (X-Upload-Session)
     */
    protected function linkRequest(string $token, string $method, string $url, ?array $data = null, ?string $session = null): ResponseInterface
    {
        $options = [
            'headers' => [
                'Authorization' => 'UploadLink '.$token,
            ],
        ];
        if (null !== $session) {
            $options['headers'][UploadLinkUser::SESSION_HEADER] = $session;
        }
        if (null !== $data) {
            $options['json'] = $data;
        }

        return static::createClient()->request($method, $url, $options);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function uploadFile(string $token, array $parameters = []): ResponseInterface
    {
        return static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'UploadLink '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(self::FIXTURE, '32x32.jpg', 'image/jpeg'),
                ],
                'parameters' => $parameters,
            ],
        ]);
    }
}
