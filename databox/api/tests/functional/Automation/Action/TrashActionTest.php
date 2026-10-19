<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Automation\Action\TrashAction;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Automation\AutomationTestTrait;
use Symfony\Component\Messenger\Envelope;

final class TrashActionTest extends AbstractDataboxTestCase
{
    use AutomationActionTestTrait;
    use AutomationTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
    }

    /**
     * @return string[][] the IDs of the assets of each AssetsDelete message
     */
    private static function getDeletedIds(array $envelopes): array
    {
        $ids = [];
        foreach ($envelopes as $envelope) {
            /** @var Envelope $envelope */
            $message = $envelope->getMessage();
            if ($message instanceof AssetsDelete) {
                self::assertFalse($message->isHardDelete());
                self::assertSame([], $message->getCollections());
                $ids[] = $message->getIds();
            }
        }

        return $ids;
    }

    public function testTrash(): void
    {
        $asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);
        $trashed = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER, 'no_flush' => true]);
        $trashed->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();

        $this->assertSame([], self::getService(TrashAction::class)->validateConfig(['unexpected' => true]));

        $transport = self::interceptTransport('p1');
        $state = $this->processAction(self::getService(TrashAction::class), [], [$asset, $trashed]);

        $this->assertSame(2, $state->getProcessed());
        $this->assertSame(0, $state->getSkipped());
        $this->assertSame(0, $state->getFailed());
        // Already in the trash: not deleted again
        $this->assertSame([[$asset->getId()]], self::getDeletedIds($transport->getSent()));
    }

    public function testTrashIsSkippedWithoutPermission(): void
    {
        $asset = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OWNER]);

        $transport = self::interceptTransport('p1');
        $state = $this->processAction(self::getService(TrashAction::class), [], [$asset], self::OTHER);

        $this->assertSame(0, $state->getProcessed());
        $this->assertSame(1, $state->getSkipped());
        $this->assertSame([['assetId' => $asset->getId(), 'message' => 'Not allowed to delete the asset']], $state->getErrors());
        $this->assertSame([], self::getDeletedIds($transport->getSent()));
        $this->assertFalse(self::getEntityManager()->find(Asset::class, $asset->getId())->isDeleted());
    }
}
