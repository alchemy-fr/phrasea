<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Rendition;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Consumer\WorkerQueue;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\IntegrationManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;

/**
 * The renditions marked "build first", and the ones they are built from, are built on the
 * heavy queue; the other ones wait on the low priority heavy queue.
 */
final class RenditionIntegrationQueueTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    public function testBuildFirstRenditionsAndTheirAncestorsGoToTheHeavyQueue(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('rendition-queues');
        $definitions = $defaults->renditionDefinitions;
        $this->assertTrue($definitions['thumbnail']->isBuildFirst());
        $this->assertFalse($definitions['preview']->isBuildFirst());
        $free = $this->createRenditionDefinition($workspace, $defaults->renditionPolicy, 'Free', [
            'parent' => $definitions['main'],
        ]);

        $jobs = $this->getJobs($workspace);

        foreach (['main', 'preview', 'thumbnail', 'storyContactSheet'] as $name) {
            $this->assertSame(WorkerQueue::HEAVY, $jobs[$definitions[$name]->getId()]->getQueue(), $name);
        }
        $this->assertSame(WorkerQueue::HEAVY_LOW, $jobs[$definitions['animatedThumbnail']->getId()]->getQueue());
        $this->assertSame(WorkerQueue::HEAVY_LOW, $jobs[$free->getId()]->getQueue());
    }

    public function testWithoutBuildFirstRenditionEveryRenditionGoesToTheLowPriorityQueue(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('rendition-queues-low');
        foreach ($defaults->renditionDefinitions as $definition) {
            $definition->setBuildFirst(false);
        }
        self::getEntityManager()->flush();

        $jobs = $this->getJobs($workspace);

        $this->assertNotEmpty($jobs);
        foreach ($jobs as $job) {
            $this->assertSame(WorkerQueue::HEAVY_LOW, $job->getQueue(), $job->getId());
        }
    }

    public function testExplicitRenditionsKeepTheHeavyQueueForTheAncestorsOfABuildFirstOne(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('rendition-queues-explicit');
        $definitions = $defaults->renditionDefinitions;

        $jobs = $this->getJobs($workspace, [$definitions['thumbnail']->getId(), $definitions['animatedThumbnail']->getId()]);

        $this->assertEqualsCanonicalizing([
            $definitions['main']->getId(),
            $definitions['preview']->getId(),
            $definitions['thumbnail']->getId(),
            $definitions['animatedThumbnail']->getId(),
        ], array_keys($jobs));
        foreach (['main', 'preview', 'thumbnail'] as $name) {
            $this->assertSame(WorkerQueue::HEAVY, $jobs[$definitions[$name]->getId()]->getQueue(), $name);
        }
        $this->assertSame(WorkerQueue::HEAVY_LOW, $jobs[$definitions['animatedThumbnail']->getId()]->getQueue());
    }

    /**
     * @param string[]|null $renditions the rendition definitions to build, all when null
     *
     * @return array<string, Job> the jobs by rendition definition ID
     */
    private function getJobs(Workspace $workspace, ?array $renditions = null): array
    {
        $workspaceIntegration = self::getEntityManager()->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $workspace->getId(),
            'integration' => RenditionIntegration::getName(),
        ]);
        if (null !== $renditions) {
            $workspaceIntegration->setConfig(['renditions' => $renditions]);
        }
        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($workspaceIntegration);

        /** @var RenditionIntegration $integration */
        $integration = $config->getIntegration();

        return iterator_to_array($integration->getWorkflowJobDefinitions($config, new Workflow('test')));
    }
}
