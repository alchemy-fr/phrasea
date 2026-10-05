<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAccess;
use App\Entity\Core\EntityList;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Tag;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Integration\WorkspaceSecret;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Model\AssetTypeEnum;
use App\Service\Workspace\Template\Section\AccessControlSection;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\TermsManager;
use App\Service\Workspace\WorkspaceDuplicateManager;
use App\Service\Workspace\WorkspaceTemplater;
use App\Tests\Functional\AbstractDataboxTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Ramsey\Uuid\Uuid;

class WorkspaceTemplaterTest extends AbstractDataboxTestCase
{
    /**
     * Workspace-scoped entities that are content or runtime state, not configuration.
     */
    private const array NOT_IN_TEMPLATE = [
        Asset::class,
        Collection::class,
        CollectionAccess::class,
        File::class,
    ];

    private const array IN_TEMPLATE = [
        AlternateUrl::class,
        AssetPolicy::class,
        AttributeDefinition::class,
        AttributeEntity::class,
        AttributeFilterRule::class,
        AttributePolicy::class,
        EntityList::class,
        RenditionDefinition::class,
        RenditionPolicy::class,
        Tag::class,
        TermsVersion::class,
        WorkspaceEnv::class,
        WorkspaceIntegration::class,
        WorkspaceSecret::class,
        AssetDataTemplate::class,
    ];

    public function testEveryWorkspaceEntityIsHandled(): void
    {
        $workspaceEntities = [];
        foreach (self::getEntityManager()->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getAssociationMappings() as $mapping) {
                if (Workspace::class === $mapping['targetEntity'] && $metadata->isAssociationWithSingleJoinColumn($mapping['fieldName'])) {
                    $workspaceEntities[] = $metadata->getName();
                    break;
                }
            }
        }

        $this->assertEqualsCanonicalizing(
            [...self::IN_TEMPLATE, ...self::NOT_IN_TEMPLATE],
            array_values(array_unique($workspaceEntities)),
            'A workspace-scoped entity must either be handled by a WorkspaceTemplater section or be declared as content.'
        );
    }

    public function testRoundTripCopiesTheWholeConfiguration(): void
    {
        $refs = $this->createFullWorkspace();
        $source = $refs['workspace'];
        $templater = $this->getTemplater();

        $exported = $templater->export($source, WorkspaceTemplateOptions::full());
        $target = $this->createEmptyWorkspace('Copy', 'copy');
        $templater->importToWorkspace($target, $exported);

        $em = self::getEntityManager();
        $em->clear();
        $target = $em->find(Workspace::class, $target->getId());
        $reExported = $templater->export($target, WorkspaceTemplateOptions::full());

        $this->assertEquals($this->normalize($exported), $this->normalize($reExported));

        // references are remapped to the imported entities
        $newRd = $em->getRepository(RenditionDefinition::class)->findOneBy(['workspace' => $target, 'key' => 'custom']);
        $newTag = $em->getRepository(Tag::class)->findOneBy(['workspace' => $target, 'name' => 'foo']);
        $newList = $em->getRepository(EntityList::class)->findOneBy(['workspace' => $target, 'name' => 'Colors']);
        $newEntity = $em->getRepository(AttributeEntity::class)->findOneBy(['list' => $newList, 'value' => 'Red']);
        $this->assertNotSame($refs['rendition']->getId(), $newRd->getId());

        /** @var AttributeDefinition $newAd */
        $newAd = $em->getRepository(AttributeDefinition::class)->findOneBy(['workspace' => $target, 'name' => 'Description']);
        $this->assertSame('legacyslug', $newAd->getSlug());
        $this->assertSame($newList->getId(), $newAd->getEntityList()->getId());
        $this->assertSame([$newRd->getId()], $newAd->getWriteMetadataRenditions()->map(fn (RenditionDefinition $rd) => $rd->getId())->getValues());

        /** @var WorkspaceIntegration $integration */
        $integration = $em->getRepository(WorkspaceIntegration::class)->findOneBy(['workspace' => $target, 'name' => 'renditions']);
        $this->assertSame(['renditions' => [$newRd->getId()]], $integration->getConfig());

        /** @var AttributeFilterRule $rule */
        $rule = $em->getRepository(AttributeFilterRule::class)->findOneBy(['workspace' => $target]);
        $this->assertSame(sprintf('@tag = "%s" OR description = "%s"', $newTag->getId(), $newEntity->getId()), $rule->getCondition());
        $this->assertSame([KeycloakClientTestMock::USER_UID], $rule->getUserIds());

        /** @var AssetPolicy $assetPolicy */
        $assetPolicy = $em->getRepository(AssetPolicy::class)->findOneBy(['workspace' => $target]);
        $this->assertSame($newRd->getId(), $assetPolicy->getActions()[0]['definitionId']);

        /** @var AssetDataTemplate $dataTemplate */
        $dataTemplate = $em->getRepository(AssetDataTemplate::class)->findOneBy(['workspace' => $target, 'name' => 'Public template']);
        $this->assertSame($newEntity->getId(), $dataTemplate->getAttributes()->first()->getValue());
        $this->assertSame($newAd->getId(), $dataTemplate->getAttributes()->first()->getDefinition()->getId());

        $this->assertTrue(self::getPermissionManager()->isGranted(
            $this->createUserFromId(KeycloakClientTestMock::OTHER_USER_UID),
            $em->find(AttributePolicy::class, $newAd->getPolicy()->getId()),
            PermissionInterface::VIEW,
        ));
    }

    public function testReimportIsIdempotent(): void
    {
        $refs = $this->createFullWorkspace();
        $source = $refs['workspace'];
        $templater = $this->getTemplater();
        $exported = $templater->export($source, WorkspaceTemplateOptions::full());
        $counts = $this->countWorkspaceEntities($source);

        $templater->importToWorkspace($source, $exported);
        self::getEntityManager()->clear();
        $source = self::getEntityManager()->find(Workspace::class, $source->getId());

        $this->assertSame($counts, $this->countWorkspaceEntities($source));
        $this->assertEquals($exported, $templater->export($source, WorkspaceTemplateOptions::full()));

        $target = $this->createEmptyWorkspace('Copy', 'copy');
        $templater->importToWorkspace($target, $exported);
        $templater->importToWorkspace($target, $exported);
        self::getEntityManager()->clear();
        $target = self::getEntityManager()->find(Workspace::class, $target->getId());

        $this->assertSame($counts, $this->countWorkspaceEntities($target));
    }

    public function testPortableExportExcludesInstanceData(): void
    {
        $refs = $this->createFullWorkspace();
        $exported = $this->getTemplater()->export($refs['workspace']);

        $this->assertSame([], $exported[AccessControlSection::getKey()]);
        $this->assertSame([['name' => 'API_KEY']], $exported['WorkspaceSecret']);
        $this->assertSame(['Public template'], array_column($exported['AssetDataTemplate'], 'name'));
        $this->assertArrayNotHasKey('userIds', $exported['AttributeFilterRule'][0]);
        $this->assertArrayNotHasKey('userIds', $exported['AssetPolicy'][0]);
        $this->assertStringNotContainsString(KeycloakClientTestMock::OTHER_USER_UID, json_encode($exported, JSON_THROW_ON_ERROR));

        $target = $this->createEmptyWorkspace('Copy', 'copy');
        $this->getTemplater()->importToWorkspace($target, $exported);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame(0, $em->getRepository(WorkspaceSecret::class)->count(['workspace' => $target->getId()]));
        /** @var EntityList $list */
        $list = $em->getRepository(EntityList::class)->findOneBy(['workspace' => $target->getId()]);
        $this->assertSame('custom_owner', $list->getOwnerId());
    }

    public function testImportLegacyTemplate(): void
    {
        $policyId = Uuid::uuid4()->toString();
        $attrPolicyId = Uuid::uuid4()->toString();
        $listId = Uuid::uuid4()->toString();
        $rd = fn (string $name, ?string $parent, bool $main) => [
            'id' => '#'.$name,
            'name' => $name,
            'policy' => $policyId,
            'parent' => $parent,
            'buildMode' => 0,
            'priority' => 0,
            'download' => true,
            'substituable' => true,
            'useAsMain' => $main,
            'useAsPreview' => false,
            'useAsThumbnail' => false,
            'useAsAnimatedThumbnail' => false,
            'labels' => null,
            'definition' => '',
        ];

        $data = [
            'Workspace' => ['public' => true, 'enabledLocales' => ['en'], 'localeFallbacks' => ['en'], 'config' => []],
            'RenditionPolicy' => [['id' => $policyId, 'name' => 'Public', 'public' => true, 'labels' => null]],
            'RenditionDefinition' => [$rd('thumbnail', '#main', false), $rd('main', null, true)],
            'EntityList' => [['id' => $listId, 'name' => 'Colors', 'entities' => [
                ['value' => 'Red', 'position' => 0, 'translations' => null, 'synonyms' => null],
            ]]],
            'AttributePolicy' => [['id' => $attrPolicyId, 'name' => 'Public', 'editable' => true, 'public' => true, 'labels' => null]],
            'AttributeDefinition' => [[
                'name' => 'Color',
                'policy' => $attrPolicyId,
                'labels' => null,
                'entityList' => $listId,
                'fallback' => null,
                'fieldType' => 'entity',
                'fileType' => null,
                'initialValues' => null,
                'writeMetadataRenditions' => ['main'],
                'position' => 0,
                'searchBoost' => null,
                'allowInvalid' => false,
                'facetEnabled' => true,
                'multiple' => false,
                'searchable' => true,
                'sortable' => false,
                'suggest' => false,
                'translatable' => false,
                'target' => 1,
                'guiEdit' => true,
            ]],
            'Tag' => [['name' => 'foo', 'color' => null, 'translations' => null, 'locale' => 'en']],
        ];

        $ws = $this->getTemplater()->import($data, 'Legacy', 'legacy', 'custom_owner');

        $em = self::getEntityManager();
        $em->clear();
        /** @var RenditionDefinition $thumbnail */
        $thumbnail = $em->getRepository(RenditionDefinition::class)->findOneBy(['workspace' => $ws->getId(), 'name' => 'thumbnail']);
        $this->assertSame('main', $thumbnail->getParent()->getName());
        /** @var AttributeDefinition $ad */
        $ad = $em->getRepository(AttributeDefinition::class)->findOneBy(['workspace' => $ws->getId(), 'name' => 'Color']);
        $this->assertSame('entity', $ad->getType());
        $this->assertSame('Colors', $ad->getEntityList()->getName());
        $this->assertSame(['main'], $ad->getWriteMetadataRenditions()->map(fn (RenditionDefinition $rd) => $rd->getName())->getValues());
    }

    public function testDuplicateWorkspaceCopiesTheConfiguration(): void
    {
        $refs = $this->createFullWorkspace();

        $newWorkspace = self::getService(WorkspaceDuplicateManager::class)->duplicateWorkspace($refs['workspace'], 'my-workspace-copy');
        $em = self::getEntityManager();
        $em->flush();
        $em->clear();

        $newWorkspace = $em->find(Workspace::class, $newWorkspace->getId());
        $this->assertSame('my-workspace-copy', $newWorkspace->getSlug());
        $this->assertSame($refs['workspace']->getName(), $newWorkspace->getName());
        $this->assertSame($this->countWorkspaceEntities($refs['workspace']), $this->countWorkspaceEntities($newWorkspace));
        $this->assertTrue(self::getPermissionManager()->isGranted(
            $this->createUserFromId(KeycloakClientTestMock::OTHER_USER_UID),
            $newWorkspace,
            PermissionInterface::VIEW,
        ));
    }

    private function getTemplater(): WorkspaceTemplater
    {
        return self::getService(WorkspaceTemplater::class);
    }

    private function createEmptyWorkspace(string $name, string $slug): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName($name);
        $workspace->setSlug($slug);
        $workspace->setOwnerId('custom_owner');
        $workspace->setEnabledLocales(['en']);

        return $workspace;
    }

    private function createUserFromId(string $userId): JwtUser
    {
        return new JwtUser('jwt', $userId, 'user');
    }

    /**
     * @return array<string, int>
     */
    private function countWorkspaceEntities(Workspace $workspace): array
    {
        $em = self::getEntityManager();
        $counts = [];
        foreach (self::IN_TEMPLATE as $class) {
            $counts[$class] = $em->getRepository($class)->count(['workspace' => $workspace->getId()]);
        }
        $counts['ace'] = count($this->getTemplater()->export($workspace, WorkspaceTemplateOptions::full())[AccessControlSection::getKey()]);

        return $counts;
    }

    /**
     * Replaces the UUIDs by a placeholder: the ids of the copies differ, the rest must be identical.
     */
    private function normalize(array $data): array
    {
        $json = preg_replace('#[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#', 'UUID', json_encode($data, JSON_THROW_ON_ERROR));

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        // once ids are replaced, the order of the items of a section is no longer meaningful
        foreach ($data as &$items) {
            if (is_array($items) && array_is_list($items)) {
                usort($items, fn (mixed $a, mixed $b): int => json_encode($a) <=> json_encode($b));
            }
        }

        return $data;
    }

    /**
     * @return array{workspace: Workspace, rendition: RenditionDefinition}
     */
    private function createFullWorkspace(): array
    {
        $em = self::getEntityManager();
        $workspace = $this->createWorkspace();
        $workspace->setPublic(true);
        $workspace->setLocaleFallbacks(['fr' => 'en']);
        $workspace->setTranslations(['name' => ['fr' => 'Mon espace']]);
        $em->persist($workspace);

        $list = new EntityList();
        $list->setWorkspace($workspace);
        $list->setName('Colors');
        $list->setOwnerId('custom_owner');
        $list->setAllowNewValues(true);
        $list->setWithColors(true);
        $em->persist($list);

        $entity = new AttributeEntity();
        $entity->setWorkspace($workspace);
        $entity->setList($list);
        $entity->setValue('Red');
        $entity->setPosition(3);
        $entity->setColor('#ff0000');
        $entity->setEmoji('🔴');
        $entity->setExternalId('ext-red');
        $entity->setStatus(AttributeEntity::STATUS_PENDING);
        $entity->setSynonyms(['en' => ['Crimson']]);
        $em->persist($entity);

        $attributePolicy = new AttributePolicy();
        $attributePolicy->setWorkspace($workspace);
        $attributePolicy->setName('Private');
        $attributePolicy->setKey('priv');
        $attributePolicy->setPublic(false);
        $attributePolicy->setEditable(false);
        $attributePolicy->setLabels(['l' => 'v']);
        $em->persist($attributePolicy);

        $renditionPolicy = $em->getRepository(RenditionPolicy::class)->findOneBy(['workspace' => $workspace]);
        $main = $em->getRepository(RenditionDefinition::class)->findOneBy(['workspace' => $workspace, 'key' => 'main']);
        $rendition = new RenditionDefinition();
        $rendition->setWorkspace($workspace);
        $rendition->setPolicy($renditionPolicy);
        $rendition->setParent($main);
        $rendition->setName('Custom');
        $rendition->setKey('custom');
        $rendition->setTarget(AssetTypeEnum::Both);
        $rendition->setTranslations(['name' => ['fr' => 'Perso']]);
        $rendition->setWriteMetadata(true);
        $rendition->setMetadata(['XMP-dc:Rights' => 'Me']);
        $rendition->setDefinition('foo: bar');
        $em->persist($rendition);

        $ad = new AttributeDefinition();
        $ad->setWorkspace($workspace);
        $ad->setPolicy($attributePolicy);
        $ad->setName('Description');
        $ad->setSlug('legacyslug');
        $ad->setKey('desc');
        $ad->setType('entity');
        $ad->setEntityList($list);
        $ad->setEnabled(false);
        $ad->setTranslations(['name' => ['fr' => 'Description FR']]);
        $ad->setWriteMetadataRenditions([$rendition]);
        $ad->setRequired(true);
        $ad->setMaxLength(42);
        $em->persist($ad);

        $foo = new Tag();
        $foo->setWorkspace($workspace);
        $foo->setName('foo');
        $foo->setColor('00ff00');
        $em->persist($foo);

        $env = new WorkspaceEnv();
        $env->setWorkspace($workspace);
        $env->setName('API_URL');
        $env->setValue('https://example.com');
        $em->persist($env);

        $secret = new WorkspaceSecret();
        $secret->setWorkspace($workspace);
        $secret->setName('API_KEY');
        $secret->setPlainValue('s3cr3t');
        $em->persist($secret);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setName('renditions');
        $integration->setIntegration('core.rendition');
        $integration->setOwnerId('custom_owner');
        $integration->setConfig(['renditions' => [$rendition->getId()]]);
        $integration->setIf('asset.source');
        $integration->setPublic(true);
        $em->persist($integration);

        $dependent = new WorkspaceIntegration();
        $dependent->setWorkspace($workspace);
        $dependent->setName('dependent');
        $dependent->setIntegration('test');
        $dependent->setOwnerId('custom_owner');
        $dependent->setConfig([]);
        $dependent->setPublic(false);
        $dependent->addNeed($integration);
        $em->persist($dependent);

        $assetPolicy = new AssetPolicy();
        $assetPolicy->setWorkspace($workspace);
        $assetPolicy->setName('Hide custom');
        $assetPolicy->setOwnerId('custom_owner');
        $assetPolicy->setPriority(5);
        $assetPolicy->setActions([['action' => 'hide_rendition', 'definitionId' => $rendition->getId()]]);
        $assetPolicyUser = new AssetPolicyUser();
        $assetPolicyUser->setPolicy($assetPolicy);
        $assetPolicyUser->setUserType(AssetPolicyUser::TYPE_USER);
        $assetPolicyUser->setUserId(KeycloakClientTestMock::OTHER_USER_UID);
        $assetPolicy->getUsers()->add($assetPolicyUser);
        $em->persist($assetPolicy);

        $alternateUrl = new AlternateUrl();
        $alternateUrl->setWorkspace($workspace);
        $alternateUrl->setType('cdn');
        $alternateUrl->setLabel('CDN');
        $em->persist($alternateUrl);

        foreach (['Public template' => true, 'Private template' => false] as $name => $public) {
            $dataTemplate = new AssetDataTemplate();
            $dataTemplate->setWorkspace($workspace);
            $dataTemplate->setName($name);
            $dataTemplate->setPublic($public);
            $dataTemplate->setOwnerId(KeycloakClientTestMock::OTHER_USER_UID);
            $dataTemplate->setPrivacy(2);
            $dataTemplate->setTags(new ArrayCollection([$foo]));
            $templateAttribute = new TemplateAttribute();
            $templateAttribute->setDefinition($ad);
            $templateAttribute->setValue($entity->getId());
            $dataTemplate->addAttribute($templateAttribute);
            $em->persist($templateAttribute);
            $em->persist($dataTemplate);
        }
        $em->flush();

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [KeycloakClientTestMock::USER_UID],
            [],
            sprintf('@tag = "%s" OR description = "%s"', $foo->getId(), $entity->getId()),
        );
        self::getService(TermsManager::class)->updateTerms($workspace, 'Be nice', ['fr' => 'Soyez sympa']);
        $em->flush();
        $this->grantUserOnObject(KeycloakClientTestMock::OTHER_USER_UID, $attributePolicy, PermissionInterface::VIEW);
        $this->grantUserOnObject(KeycloakClientTestMock::OTHER_USER_UID, $workspace, PermissionInterface::VIEW);

        return [
            'workspace' => $workspace,
            'rendition' => $rendition,
        ];
    }
}
