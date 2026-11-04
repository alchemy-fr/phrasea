<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\Workflow\Message\JobConsumer;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class IngestWorkspaceAssetsCommandTest extends AbstractDataboxTestCase
{
    public function testIngestsTheAssetsHavingASource(): void
    {
        $workspace = $this->createWorkspace(['slug' => 'ingested']);
        $other = $this->createWorkspace(['slug' => 'not-ingested']);

        $withSource = $this->createAsset(['workspace' => $workspace, 'ownerId' => 'asset_owner', 'no_flush' => true]);
        $withSource->setSource($this->createFile($workspace));
        $story = $this->createAsset(['workspace' => $workspace]);
        $otherAsset = $this->createAsset(['workspace' => $other, 'no_flush' => true]);
        $otherAsset->setSource($this->createFile($other));
        self::getEntityManager()->flush();

        // The jobs of the workflow are not run
        $p2 = self::interceptTransport('p2');

        $tester = $this->runIngestCommand(['workspaces' => ['ingested']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('1 asset(s) ingested, 1 skipped (no source), 0 error(s)', $tester->getDisplay());

        $states = $this->findWorkflowStates($withSource->getId());
        $this->assertCount(1, $states);
        $this->assertSame(AssetIngestWorkflowEvent::getWorkflowName($workspace->getId()), $states[0]->getName());
        $this->assertSame('asset_owner', $states[0]->getInitiatorId());
        $this->assertNotEmpty(array_filter($p2->getSent(), fn ($envelope): bool => $envelope->getMessage() instanceof JobConsumer));

        $this->assertSame([], $this->findWorkflowStates($story->getId()));
        $this->assertSame([], $this->findWorkflowStates($otherAsset->getId()));
    }

    public function testIngestsEveryWorkspaceByDefault(): void
    {
        $workspace = $this->createWorkspace(['slug' => 'first']);
        $other = $this->createWorkspace(['slug' => 'second']);

        $asset = $this->createAsset(['workspace' => $workspace, 'no_flush' => true]);
        $asset->setSource($this->createFile($workspace));
        $otherAsset = $this->createAsset(['workspace' => $other, 'no_flush' => true]);
        $otherAsset->setSource($this->createFile($other));
        self::getEntityManager()->flush();

        self::interceptTransport('p2');

        $tester = $this->runIngestCommand();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('2 asset(s) ingested, 0 skipped (no source), 0 error(s)', $tester->getDisplay());
        $this->assertCount(1, $this->findWorkflowStates($asset->getId()));
        $this->assertCount(1, $this->findWorkflowStates($otherAsset->getId()));
    }

    public function testUnknownWorkspaceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workspace "unknown" not found');

        $this->runIngestCommand(['workspaces' => ['unknown']]);
    }

    private function runIngestCommand(array $input = []): CommandTester
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:workspace:ingest-assets'));
        $tester->execute($input);

        return $tester;
    }

    /**
     * @return WorkflowState[]
     */
    private function findWorkflowStates(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->getRepository(WorkflowState::class)->findBy(['asset' => $assetId]);
    }

    private function createFile(Workspace $workspace): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setType('image/jpeg');
        $file->setPath('test/'.uniqid().'.jpg');
        self::getEntityManager()->persist($file);

        return $file;
    }

    private static function interceptTransport(string $name): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.'.$name);

        return $transport->intercept();
    }
}
