<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rendition;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Rendition definitions of a non-public policy are listed only to users granted
 * VIEW on that policy, through an ACE stored with the rendition_policy object type.
 */
class RenditionDefinitionAclTest extends AbstractDataboxTestCase
{
    private const string USER_ID = KeycloakClientTestMock::USER_UID;

    public function testViewOnRenditionPolicyListsItsDefinitions(): void
    {
        $definition = $this->createPrivateRenditionDefinition();
        $this->assertNotContains($definition->getId(), $this->listRenditionDefinitionIds());

        $this->grantUserOnObject(self::USER_ID, $definition->getPolicy(), PermissionInterface::VIEW);

        $this->assertContains($definition->getId(), $this->listRenditionDefinitionIds());
    }

    public function testViewOnAttributePolicyWithSameIdDoesNotGrantIt(): void
    {
        $definition = $this->createPrivateRenditionDefinition();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            self::USER_ID,
            AttributePolicy::OBJECT_TYPE,
            $definition->getPolicy()->getId(),
            PermissionInterface::VIEW,
        );

        $this->assertNotContains($definition->getId(), $this->listRenditionDefinitionIds());
    }

    private function createPrivateRenditionDefinition(): RenditionDefinition
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $this->addUserOnWorkspace(self::USER_ID, $workspace->getId());

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName('Private policy');
        $policy->setEditable(true);
        $policy->setPublic(false);
        $em->persist($policy);

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName('Private rendition');
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $em->persist($definition);
        $em->flush();

        return $definition;
    }

    /**
     * @return string[]
     */
    private function listRenditionDefinitionIds(): array
    {
        $response = static::createClient()->request('GET', '/rendition-definitions', [
            'query' => ['workspaceId' => $this->getOrCreateDefaultWorkspace()->getId()],
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER_ID),
            ],
        ]);

        return array_column($this->getDataFromResponse($response, 200)['member'], 'id');
    }
}
