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
use App\Tests\Functional\AbstractUploaderTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractUploadLinkTestCase extends AbstractUploaderTestCase
{
    protected const string FIXTURE = __DIR__.'/../../fixtures/32x32.jpg';
    protected const int FIXTURE_SIZE = 846;

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
     * @param array{enabled?: bool, expiresAt?: ?\DateTimeImmutable, maxAssets?: ?int, maxTotalSize?: ?int, name?: ?string} $options
     */
    protected function createLink(Target $target, string $ownerId = KeycloakClientTestMock::USER_UID, array $options = []): UploadLink
    {
        $link = new UploadLink();
        $link->setTarget($target);
        $link->setOwnerId($ownerId);
        $link->setName($options['name'] ?? 'Photographer');
        $link->setEnabled($options['enabled'] ?? true);
        $link->setExpiresAt($options['expiresAt'] ?? null);
        $link->setMaxAssets($options['maxAssets'] ?? null);
        $link->setMaxTotalSize($options['maxTotalSize'] ?? null);

        $em = self::getEntityManager();
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

    protected function linkRequest(string $token, string $method, string $url, ?array $data = null): ResponseInterface
    {
        $options = [
            'headers' => [
                'Authorization' => 'UploadLink '.$token,
            ],
        ];
        if (null !== $data) {
            $options['json'] = $data;
        }

        return static::createClient()->request($method, $url, $options);
    }

    /**
     * @param array<string, string> $parameters
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
