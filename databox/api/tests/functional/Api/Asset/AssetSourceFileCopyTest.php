<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * A new asset may copy the file of an existing one ("sourceFileId"),
 * only when the user can read that file.
 */
class AssetSourceFileCopyTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    public function testOnlyTheFileOfAReadableAssetIsCopied(): void
    {
        $client = static::createClient();
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        $secretFile = $this->createSourceFile($workspace);
        $secret = $this->createAsset(['ownerId' => self::ADMIN, 'no_flush' => true]);
        $secret->setSource($secretFile);
        $mineFile = $this->createSourceFile($workspace);
        $mine = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $mine->setSource($mineFile);
        $em->flush();

        $this->send($client, 'POST', '/assets', self::USER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFileId' => $secretFile->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->send($client, 'POST', '/assets', self::USER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFileId' => $mineFile->getId(),
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    private function createSourceFile(Workspace $workspace): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setType('image/jpeg');
        self::getEntityManager()->persist($file);

        return $file;
    }

    /**
     * A workspace of the admin, the user being one of its readers (and more if $extraPermissions).
     */
    private function createMemberWorkspace(int $extraPermissions = 0): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | $extraPermissions);

        return $workspace;
    }

    private function send(Client $client, string $method, string $uri, string $userId, array $json): void
    {
        $client->request($method, $uri, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => $json,
        ]);
    }
}
