<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Import\DispatchAssetImport;
use App\Consumer\Handler\Import\DispatchAssetImportHandler;
use App\Consumer\Handler\Import\ImportAssetEntry;
use App\Consumer\Handler\Import\ImportAssetEntryHandler;
use App\Entity\Core\AssetImport;
use App\Entity\Core\Workspace;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The import messages are kept queued, then consumed by calling their handlers; the
 * workflows and the file imports are never run (no download).
 */
trait AssetImportTestTrait
{
    use RenditionTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private InMemoryTransport $taskQueue;
    private InMemoryTransport $p2Queue;
    /**
     * @var array<int, object> the handled messages (kept alive for their object ID)
     */
    private array $consumedTaskMessages = [];

    private function interceptQueues(): void
    {
        self::bootKernel();
        /** @var TestTransport $task */
        $task = self::getContainer()->get('messenger.transport.task');
        $this->taskQueue = $task->intercept();
        /** @var TestTransport $p2 */
        $p2 = self::getContainer()->get('messenger.transport.p2');
        $this->p2Queue = $p2->intercept();
        $this->consumedTaskMessages = [];
    }

    /**
     * The kernel is not rebooted between the requests of a test, so that the queues stay intercepted.
     */
    private function apiRequest(string $method, string $uri, ?string $userId, ?array $json = null, array $options = []): ResponseInterface
    {
        self::getEntityManager()->clear();

        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }
        if (null !== $json) {
            $options['json'] = $json;
        }

        $client = static::createClient();
        $client->getKernelBrowser()->disableReboot();

        return $client->request($method, $uri, $options);
    }

    private function postImport(Workspace $workspace, string $data, array $extra = [], string $userId = self::OWNER): ResponseInterface
    {
        return $this->apiRequest('POST', '/asset-imports', $userId, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'format' => AssetImport::FORMAT_NDJSON,
            'data' => $data,
            ...$extra,
        ]);
    }

    /**
     * @param array<array<string, mixed>|string> $entries one line each (a string is written as is)
     */
    private static function ndjson(array $entries): string
    {
        return implode("\n", array_map(
            static fn (array|string $entry): string => is_string($entry) ? $entry : json_encode($entry, JSON_THROW_ON_ERROR),
            $entries
        ))."\n";
    }

    /**
     * Handles the queued import messages, and those they queue, until none is left.
     *
     * @param class-string|null $only handle the messages of this class only (the others stay queued)
     */
    private function consumeImportMessages(?string $only = null): void
    {
        do {
            $handled = false;
            foreach ($this->taskQueue->getSent() as $envelope) {
                $message = $envelope->getMessage();
                $id = spl_object_id($message);
                if (isset($this->consumedTaskMessages[$id]) || (null !== $only && !$message instanceof $only)) {
                    continue;
                }

                $this->consumedTaskMessages[$id] = $message;
                match (true) {
                    $message instanceof DispatchAssetImport => self::getService(DispatchAssetImportHandler::class)($message),
                    $message instanceof ImportAssetEntry => self::getService(ImportAssetEntryHandler::class)($message),
                    default => null,
                };
                $handled = true;
            }
        } while ($handled);

        self::getEntityManager()->clear();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T[]
     */
    private function getQueuedMessages(InMemoryTransport $queue, string $class): array
    {
        return array_values(array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $queue->getSent()),
            static fn (object $message): bool => $message instanceof $class
        ));
    }

    private function findImport(string $id): AssetImport
    {
        self::getEntityManager()->clear();

        return self::getEntityManager()->find(AssetImport::class, $id);
    }
}
