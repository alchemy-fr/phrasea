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
final readonly class DownloadHandler
{
    public function __construct(
        private FileStorageManager $storageManager,
        private HttpClientInterface $client,
        private AssetManager $assetManager,
        private MessageBusInterface $bus,
        private PathGenerator $pathGenerator,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(Download $message): void
    {
        $url = $message->getUrl();
        $userId = $message->getUserId();
        $targetId = $message->getTargetId();
        $formData = $message->getFormData();
        $locale = $message->getLocale();
        $response = $this->client->request('GET', $url);
        $headers = $response->getHeaders();
        $contentType = $headers['content-type'][0] ?? 'application/octet-stream';

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

        $target = DoctrineUtil::findStrict($this->em, Target::class, $targetId);
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

        $this->em->persist($commit);
        $this->em->flush();

        $this->bus->dispatch($commit->toMessage());
    }
}
