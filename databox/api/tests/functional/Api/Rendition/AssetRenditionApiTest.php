<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Service\Asset\RenditionBuildHashManager;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /renditions: listing (asset filter, ACL), reading (anonymous, outsider),
 * upload, replacement, substitution, deletion and dependent renditions.
 */
final class AssetRenditionApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string READER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    private const string BUILD_DEFINITION = <<<'YAML'
image:
    transformations:
        -
            module: imagine
            options:
                filters:
                    thumbnail:
                        size: [100, 100]
YAML;

    private static function sourceFile(string $url = 'https://cdn.example.com/uploaded.jpg'): array
    {
        return [
            'url' => $url,
            'originalName' => basename($url),
            'type' => 'image/jpeg',
            'isPrivate' => false,
            'importFile' => false,
        ];
    }

    public function testListRequiresAReadableAsset(): void
    {
        [$wsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $foreignAsset = $this->createAsset(['workspace' => $wsB, 'ownerId' => 'someone-else']);

        $this->jsonRequest('GET', '/renditions', self::EDITOR);
        $this->assertResponseStatusCodeSame(400);

        $this->jsonRequest('GET', '/renditions', self::EDITOR, options: ['query' => ['assetId' => 'f1b4b4a8-0000-4000-8000-000000000000']]);
        $this->assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', '/renditions', self::EDITOR, options: ['query' => ['assetId' => $foreignAsset->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', '/renditions', self::ADMIN, options: ['query' => ['assetId' => $foreignAsset->getId()]]);
        $this->assertResponseIsSuccessful();
    }

    public function testListIsOrderedByDefinitionPriorityAndSerialized(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $low = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'low', ['priority' => -5]);
        $high = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'high', ['priority' => 5]);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $file = $this->createUrlFile($ws, 'https://cdn.example.com/high.jpg');
        $lowRendition = $this->createAssetRendition($asset, $low, null);
        $highRendition = $this->createAssetRendition($asset, $high, $file, ['substituted' => true, 'locked' => true]);

        $response = $this->jsonRequest('GET', '/renditions', self::EDITOR, options: ['query' => ['assetId' => $asset->getId()]]);
        $this->assertResponseIsSuccessful();
        $members = $response->toArray()['hydra:member'];
        $this->assertSame([$highRendition->getId(), $lowRendition->getId()], array_column($members, 'id'));

        $this->assertSame('high', $members[0]['name']);
        $this->assertSame('high', $members[0]['displayName']);
        $this->assertTrue($members[0]['ready']);
        $this->assertTrue($members[0]['substituted']);
        $this->assertTrue($members[0]['locked']);
        $this->assertSame('https://cdn.example.com/high.jpg', $members[0]['file']['url']);
        $this->assertArrayHasKey('dirty', $members[0]);

        $this->assertFalse($members[1]['ready'], 'A rendition without file is not ready');
        $this->assertNull($members[1]['file'] ?? null);
    }

    public function testAnonymousCanReadRenditionsOfAPublicAsset(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-public', self::EDITOR, true);
        $restricted = $this->createRenditionPolicy($ws, 'Restricted', false, false);
        $hdDefinition = $this->createRenditionDefinition($ws, $restricted, 'hd');
        $publicAsset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR, 'public' => true]);
        $privateAsset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $file = $this->createUrlFile($ws);
        $main = $this->createAssetRendition($publicAsset, $defaults->renditionDefinitions['main'], $file);
        $this->createAssetRendition($publicAsset, $hdDefinition, $file);
        $this->createAssetRendition($privateAsset, $defaults->renditionDefinitions['main'], $file);

        $response = $this->jsonRequest('GET', '/renditions', null, options: ['query' => ['assetId' => $publicAsset->getId()]]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([$main->getId()], $this->memberIds($response), 'Renditions of a non-public policy are hidden');

        $this->jsonRequest('GET', '/renditions/'.$main->getId(), null);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['id' => $main->getId(), 'ready' => true]);

        $this->jsonRequest('GET', '/renditions', null, options: ['query' => ['assetId' => $privateAsset->getId()]]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testRenditionOfAnUnreadableAssetIsNotReadable(): void
    {
        $this->markTestIncomplete('BUG: AssetRenditionVoter::doVote() only checks the rendition policy for READ (src/Security/Voter/AssetRenditionVoter.php:61), never the asset: anyone (even anonymous) can GET /renditions/{id} — file URL included — of a private asset in a private workspace when the policy is public.');

        [$wsB, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $asset = $this->createAsset(['workspace' => $wsB, 'ownerId' => 'someone-else']);
        $rendition = $this->createAssetRendition($asset, $defaultsB->renditionDefinitions['main'], $this->createUrlFile($wsB));

        $this->jsonRequest('GET', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', '/renditions/'.$rendition->getId(), null);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadRenditionByDefinitionName(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);

        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'rendition',
            'name' => 'Preview',
            'ready' => true,
            'substituted' => false,
            'locked' => false,
            'file' => ['url' => 'https://cdn.example.com/uploaded.jpg'],
        ]);
        $renditionId = $response->toArray()['id'];

        // Posting again on the same definition replaces the file of the same rendition
        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'sourceFile' => self::sourceFile('https://cdn.example.com/replaced.jpg'),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($renditionId, $response->toArray()['id']);
        $this->assertSame('https://cdn.example.com/replaced.jpg', $response->toArray()['file']['url']);

        $ids = $this->memberIds($this->jsonRequest('GET', '/renditions', self::EDITOR, options: ['query' => ['assetId' => $asset->getId()]]));
        $this->assertSame([$renditionId], $ids);
    }

    public function testUploadOfTheMainRenditionSetsTheMissingAssetSource(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $this->assertNull($asset->getSource());

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $defaults->renditionDefinitions['main']->getId(),
            'sourceFile' => self::sourceFile('https://cdn.example.com/source.jpg'),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame('https://cdn.example.com/source.jpg', $em->find(Asset::class, $asset->getId())->getSource()?->getPath());
    }

    public function testUploadRights(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $editorAsset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $editorAsset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $readerAsset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::READER]);

        $payload = fn (Asset $asset): array => [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'sourceFile' => self::sourceFile(),
        ];

        $this->jsonRequest('POST', '/renditions', null, $payload($editorAsset));
        $this->assertResponseStatusCodeSame(401);

        // Can read, but not edit, the asset
        $this->jsonRequest('POST', '/renditions', self::READER, $payload($editorAsset));
        $this->assertResponseStatusCodeSame(403);

        // Asset owner (not workspace editor)
        $this->jsonRequest('POST', '/renditions', self::READER, $payload($readerAsset));
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/renditions', self::ADMIN, $payload($editorAsset));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testUploadValidation(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(400);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => 'f1b4b4a8-0000-4000-8000-000000000000',
            'name' => 'Preview',
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Definition of another workspace
        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $defaultsB->renditionDefinitions['main']->getId(),
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('same workspace', $response->toArray(false)['hydra:description']);
    }

    public function testUploadWithAnUnknownDefinitionNameIsABadRequest(): void
    {
        $this->markTestIncomplete('BUG: RenditionManager::getRenditionDefinitionByName() throws \InvalidArgumentException (src/Service/Storage/RenditionManager.php:268), unmapped: POST /renditions with an unknown "name" answers 500 instead of 400.');

        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Unknown',
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSubstitution(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $previewId = $defaults->renditionDefinitions['preview']->getId();

        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $previewId,
            'substituted' => true,
            'sourceFile' => self::sourceFile('https://cdn.example.com/substitute.jpg'),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertTrue($response->toArray()['substituted']);

        // A substitution can only be overwritten by a regular upload when forced
        // (the rejection without "force" is covered by testSubstitutionErrorsAreClientErrors)
        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $previewId,
            'force' => true,
            'sourceFile' => self::sourceFile('https://cdn.example.com/built.jpg'),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertFalse($response->toArray()['substituted']);
        $this->assertSame('https://cdn.example.com/built.jpg', $response->toArray()['file']['url']);
    }

    public function testSubstitutionErrorsAreClientErrors(): void
    {
        $this->markTestIncomplete('BUG: RenditionManager::validateSubstitution() throws \InvalidArgumentException / RenditionBuildException (src/Service/Storage/RenditionManager.php:140-148), unmapped: substituting a non-substitutable or locked rendition, or overwriting a substitution without "force", answers 500 instead of 400/409.');

        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $notSubstitutable = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'fixed', ['substitutable' => false]);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $this->createAssetRendition($asset, $defaults->renditionDefinitions['thumbnail'], $this->createUrlFile($ws), ['locked' => true]);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $notSubstitutable->getId(),
            'substituted' => true,
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(400);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Thumbnail',
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(400);

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'substituted' => true,
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'sourceFile' => self::sourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testPutReplacesTheRenditionFile(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $rendition = $this->createAssetRendition($asset, $defaults->renditionDefinitions['preview'], $this->createUrlFile($ws));
        $iri = '/renditions/'.$rendition->getId();

        $this->jsonRequest('PUT', $iri, self::READER, ['sourceFile' => self::sourceFile('https://cdn.example.com/put.jpg')]);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->jsonRequest('PUT', $iri, self::EDITOR, [
            'substituted' => true,
            'sourceFile' => self::sourceFile('https://cdn.example.com/put.jpg'),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($rendition->getId(), $response->toArray()['id']);
        $this->assertTrue($response->toArray()['substituted']);
        $this->assertSame('https://cdn.example.com/put.jpg', $response->toArray()['file']['url']);
    }

    public function testPatchWithoutFileKeepsTheCurrentFile(): void
    {
        $this->markTestIncomplete('BUG: AssetRenditionInputTransformer passes a null $file to RenditionManager::createOrReplaceRenditionFile(File $file) when PUT/PATCH carries no file (src/Api/InputTransformer/AssetRenditionInputTransformer.php:72): TypeError, 500.');

        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $rendition = $this->createAssetRendition($asset, $defaults->renditionDefinitions['preview'], $this->createUrlFile($ws, 'https://cdn.example.com/kept.jpg'));

        $response = $this->jsonRequest('PATCH', '/renditions/'.$rendition->getId(), self::EDITOR, ['substituted' => true]);
        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->toArray()['substituted']);
        $this->assertSame('https://cdn.example.com/kept.jpg', $response->toArray()['file']['url']);
    }

    public function testDeleteRendition(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $rendition = $this->createAssetRendition($asset, $defaults->renditionDefinitions['preview'], $this->createUrlFile($ws));
        $iri = '/renditions/'.$rendition->getId();

        $this->jsonRequest('DELETE', $iri, null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', $iri, self::READER);
        $this->assertResponseIsSuccessful();
        $this->jsonRequest('DELETE', $iri, self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('DELETE', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame([], $this->memberIds($this->jsonRequest('GET', '/renditions', self::EDITOR, options: ['query' => ['assetId' => $asset->getId()]])));
    }

    public function testDependentRenditionBecomesDirtyWhenItsParentChanges(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $main = $defaults->renditionDefinitions['main'];
        $child = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'square', [
            'parent' => $main,
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => self::BUILD_DEFINITION,
        ]);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $mainFile = $this->createUrlFile($ws, 'https://cdn.example.com/main.jpg');
        $this->createAssetRendition($asset, $main, $mainFile);

        /** @var RenditionBuildHashManager $hashManager */
        $hashManager = self::getService(RenditionBuildHashManager::class);
        $childRendition = $this->createAssetRendition($asset, $child, $this->createUrlFile($ws, 'https://cdn.example.com/square.jpg'), [
            // Built from the current parent file
            'buildHash' => $hashManager->getBuildHash($mainFile, $child),
        ]);

        $this->jsonRequest('GET', '/renditions/'.$childRendition->getId(), self::EDITOR);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['dirty' => false]);

        // Replacing the parent file makes the child outdated
        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $main->getId(),
            'sourceFile' => self::sourceFile('https://cdn.example.com/main-v2.jpg'),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('GET', '/renditions/'.$childRendition->getId(), self::EDITOR);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['dirty' => true]);
    }

    public function testDependentRenditionIsDirtyWhenItsDefinitionChanges(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $child = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'square', [
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => self::BUILD_DEFINITION,
        ]);
        $sourceFile = $this->createUrlFile($ws, 'https://cdn.example.com/source.jpg');
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setSource($sourceFile);
        self::getEntityManager()->flush();

        /** @var RenditionBuildHashManager $hashManager */
        $hashManager = self::getService(RenditionBuildHashManager::class);
        $rendition = $this->createAssetRendition($asset, $child, $this->createUrlFile($ws, 'https://cdn.example.com/square.jpg'), [
            'buildHash' => $hashManager->getBuildHash($sourceFile, $child),
        ]);

        $this->jsonRequest('GET', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertJsonContains(['dirty' => false]);

        $this->jsonRequest('PATCH', '/rendition-definitions/'.$child->getId(), self::EDITOR, [
            'definition' => str_replace('100, 100', '200, 200', self::BUILD_DEFINITION),
        ]);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertJsonContains(['dirty' => true]);
    }
}
