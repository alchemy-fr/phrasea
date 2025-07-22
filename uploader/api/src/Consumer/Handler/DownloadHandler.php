<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class DownloadHandler
{
    public function __construct(
        private readonly FileStorageManager $storageManager,
        private readonly HttpClientInterface $client,
        private readonly AssetManager $assetManager,
        private readonly MessageBusInterface $bus,
        private readonly PathGenerator $pathGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(Download $message): void
    {
        $url = $message->url;
        $response = $this->client->request('GET', $message->url);
        $headers = $response->getHeaders();
        $contentType = $headers['content-type'][0] ?? 'application/octet-stream';
        $contentType = trim(explode(';', $contentType, 2)[0]);

        $originalName = $url;
        $originalName = explode('?', $originalName, 2)[0];
        if (1 === preg_match('#^[a-z]+://[^/]+/?$#', $originalName)) {
            $originalName = rtrim($originalName, '/');
            $originalName .= '/index';
        } else {
            $originalName = basename($originalName);
        }
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

        $target = DoctrineUtil::findStrict($this->em, Target::class, $message->targetId);

        $asset = $this->assetManager->createAsset(
            $target,
            $path,
            $contentType,
            $originalName,
            $size,
            $message->userId,
            $message->data
        );

        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setTotalSize($size);
        $commit->setLocale($message->locale);
        $commit->setFormData($message->formData ?? []);
        $commit->setUserId($message->userId);
        $commit->setFiles([$asset->getId()]);
        $commit->generateToken();

        $this->bus->dispatch($commit->toMessage());
    }
}
