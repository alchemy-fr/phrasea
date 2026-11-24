<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class AdminFiltersTest extends AbstractDataboxAdminTestCase
{
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
        $this->client->request('GET', '/admin/attribute', ['filters' => [
            'asset' => ['comparison' => '=', 'value' => Uuid::uuid4()->toString()],
        ]]);
        $this->assertResponseIsSuccessful();
    }
}
