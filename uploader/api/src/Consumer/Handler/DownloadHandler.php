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
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DownloadHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'download';

    public function __construct(
        private readonly FileStorageManager $storageManager,
        private readonly HttpClientInterface $client,
        private readonly AssetManager $assetManager,
        private readonly EventProducer $eventProducer,
        private readonly PathGenerator $pathGenerator
    ) {
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

        $originalName = basename(explode('?', (string) $url, 2)[0]);
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

        $content = $response->getContent();
        $size = strlen($content);
        $this->storageManager->store($path, $content);

        $em = $this->getEntityManager();
        $target = $em->find(Target::class, $targetId);
        if (!$target instanceof Target) {
            throw new \InvalidArgumentException(sprintf('Target "%s" not found', $targetId));
        }

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
