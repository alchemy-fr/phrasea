<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine\Listener;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Search\ComputeCollectionBranch;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The collection_access rows and the indexed branch are recomputed when a
 * field they derive from changes. collection_access is not computed on
 * SQLite: the test checks the messages sent instead.
 */
final class CollectionListenerTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    public function testOwnerChangeRecomputesTheBranch(): void
    {
        $collection = $this->createCollection(['name' => 'Owned', 'ownerId' => self::USER]);
        $id = $collection->getId();
        $inMemory = $this->interceptTransports();

        $collection->setOwnerId(self::ADMIN);
        self::getEntityManager()->flush();

        $this->assertSame([$id], $this->sentCollectionIds($inMemory, ComputeCollectionBranch::class));
        $this->assertSame([$id], $this->sentCollectionIds($inMemory, IndexCollectionBranch::class));
    }

    public function testRenameDoesNotRecomputeTheBranch(): void
    {
        $collection = $this->createCollection(['name' => 'Owned', 'ownerId' => self::USER]);
        $inMemory = $this->interceptTransports();

        $collection->setName('Renamed');
        self::getEntityManager()->flush();

        $this->assertSame([], $this->sentCollectionIds($inMemory, ComputeCollectionBranch::class));
        $this->assertSame([], $this->sentCollectionIds($inMemory, IndexCollectionBranch::class));
    }

    /**
     * To call after the creation of the collection, whose postPersist already
     * sends a ComputeCollectionBranch.
     *
     * @return InMemoryTransport[] ComputeCollectionBranch goes to p1, IndexCollectionBranch to heavy
     */
    private function interceptTransports(): array
    {
        return array_map(static function (string $name): InMemoryTransport {
            /** @var TestTransport $transport */
            $transport = static::getContainer()->get('messenger.transport.'.$name);

            return $transport->intercept();
        }, ['p1', 'heavy']);
    }

    /**
     * @param InMemoryTransport[]                                         $transports
     * @param class-string<ComputeCollectionBranch|IndexCollectionBranch> $class
     *
     * @return list<string|null>
     */
    private function sentCollectionIds(array $transports, string $class): array
    {
        $ids = [];
        foreach ($transports as $inMemory) {
            foreach ($inMemory->getSent() as $envelope) {
                $message = $envelope->getMessage();
                if ($message instanceof $class) {
                    $ids[] = $message->getCollectionId();
                }
            }
        }

        return $ids;
    }
}
