<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use GuzzleHttp\Client;
use Symfony\Component\Mime\MimeTypes;

class DownloadHandler extends AbstractEntityManagerHandler
{
    public const EVENT = 'download';

    private Client $client;
    private FileStorageManager $storageManager;
    private AssetManager $assetManager;
    private EventProducer $eventProducer;
    private PathGenerator $pathGenerator;

    public function __construct(
        FileStorageManager $storageManager,
        Client $client,
        AssetManager $assetManager,
        EventProducer $eventProducer,
        PathGenerator $pathGenerator
    ) {
        $this->client = $client;
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->eventProducer = $eventProducer;
        $this->pathGenerator = $pathGenerator;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $url = $payload['url'];
        $userId = $payload['user_id'];
        $targetId = $payload['target_id'];
        $formData = $payload['form_data'];
        $locale = $payload['locale'];
        $response = $this->client->request('GET', $url);
        $headers = $response->getHeaders();
        $contentType = $headers['Content-Type'][0] ?? 'application/octet-stream';

        $originalName = basename(explode('?', $url, 2)[0]);
        if (isset($headers['Content-Disposition'][0])) {
            $contentDisposition = $headers['Content-Disposition'][0];
            if (preg_match('#\s+filename="(.+?)"#', $contentDisposition, $regs)) {
                $originalName = $regs[1];
            }
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = !empty($extension) ? $extension : null;
        if (!$extension && 'application/octet-stream' !== $contentType) {
            $mimes = new MimeTypes();
            $extension = $mimes->getExtensions($contentType)[0];
            $originalName .= '.'.$extension;
        }

        $path = $this->pathGenerator->generatePath($extension);

        $this->storageManager->store($path, $response->getBody()->getContents());

        $em = $this->getEntityManager();
        $target = $em->find(Target::class, $targetId);
        if (!$target instanceof Target) {
            throw new \InvalidArgumentException(sprintf('Target "%s" not found', $targetId));
        }

        $size = $response->getBody()->getSize();
        $asset = $this->assetManager->createAsset(
            $target,
            $path,
            $contentType,
            $originalName,
            $size,
            $userId
        );

        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setTotalSize($size);
        $commit->setLocale($locale);
        $commit->setFormData($formData);
        $commit->setUserId($userId);
        $commit->setFiles([$asset->getId()]);
        $commit->generateToken();

        $em->persist($commit);
        $em->flush();

        $this->eventProducer->publish(new EventMessage(CommitHandler::EVENT, $commit->toArray()));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'download_url';
    }
}
