<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\TestBundle\Helper\TestServicesTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use App\Tests\Functional\DataboxTestTrait;
use Doctrine\ORM\EntityManagerInterface;

class AdminFiltersTest extends AbstractDataboxAdminTestCase
{
    use DataboxTestTrait;
    use TestServicesTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
    }

    public function testFilterOnTheWorkspaceOfTheDefinition(): void
    {
        $workspace = new Workspace();
        $workspace->setName('Filtered workspace');
        $workspace->setSlug('filtered-workspace');
        $workspace->setOwnerId('custom_owner');
        $workspace->setEnabledLocales(['en']);
        static::getContainer()->get(WorkspaceCreator::class)->createWorkspace($workspace);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (['/admin/attribute', '/admin/asset-rendition'] as $uri) {
            $this->client->request('GET', $uri, ['filters' => [
                'definition__workspace' => ['comparison' => '=', 'value' => $workspace->getId()],
            ]]);
            $this->assertResponseIsSuccessful($uri);
        }
    }

    public function testFilterOnAnAssociationIdentifier(): void
    {
        $definition = $this->createAttributeDefinition(['name' => 'Filtered definition']);
        $kept = $this->createAttribute($this->createAsset(), $definition);
        $filteredOut = $this->createAttribute($this->createAsset(), $definition);

        $crawler = $this->client->request('GET', '/admin/attribute', ['filters' => [
            'asset' => $kept->getAsset()->getId(),
        ]]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter(sprintf('tr[data-id="%s"]', $kept->getId())));
        $this->assertCount(0, $crawler->filter(sprintf('tr[data-id="%s"]', $filteredOut->getId())));
    }

    public function testFilterOnAnInvalidUuidMatchesNothing(): void
    {
        $attribute = $this->createAttribute($this->createAsset(), $this->createAttributeDefinition(['name' => 'Filtered definition']));

        foreach ([
            '/admin/attribute' => ['asset' => 'not-a-uuid'],
            '/admin/asset' => ['id' => 'not-a-uuid'],
        ] as $uri => $filters) {
            $crawler = $this->client->request('GET', $uri, ['filters' => $filters]);
            $this->assertResponseIsSuccessful($uri);
            $this->assertCount(0, $crawler->filter('tr[data-id]'), $uri);
        }

        $crawler = $this->client->request('GET', '/admin/asset', ['filters' => ['id' => $attribute->getAsset()->getId()]]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter(sprintf('tr[data-id="%s"]', $attribute->getAsset()->getId())));
    }

    private function createAttribute(Asset $asset, AttributeDefinition $definition): Attribute
    {
        $attribute = new Attribute();
        $attribute->setAsset($asset);
        $attribute->setDefinition($definition);
        $attribute->setValue('value');
        $attribute->setOrigin(Attribute::ORIGIN_HUMAN);

        $em = $this->getEntityManager();
        $em->persist($attribute);
        $em->flush();

        return $attribute;
    }
}
