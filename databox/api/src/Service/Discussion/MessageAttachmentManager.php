<?php

declare(strict_types=1);

namespace App\Service\Discussion;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Upload\UploadManager;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Consumer\Handler\File\DeleteFilesIfOrphan;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Discussion\Message;
use App\Entity\Discussion\Thread;
use App\Service\Asset\FileUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Files attached to discussion messages.
 *
 * A message attachment is `{"type": "...", "content": "<JSON>"}`. A new file
 * attachment is posted as `{"type": "file", "multipart": {"uploadId": "...",
 * "parts": [...]}}`: the upload is completed into a `File` of the workspace
 * of the thread object, and stored as
 * `{"type": "file", "content": "{\"id\": ..., \"name\": ..., \"type\": ..., \"size\": ...}"}`.
 * The download URL is added to the content on output (signed URLs expire).
 */
final readonly class MessageAttachmentManager
{
    public const string TYPE_FILE = 'file';

    public function __construct(
        private EntityManagerInterface $em,
        private UploadManager $uploadManager,
        private DiscussionManager $discussionManager,
        private FileUrlResolver $fileUrlResolver,
        private PostFlushStack $postFlushStack,
    ) {
    }

    /**
     * @param string $ownerId user identifier of the author, who must own the uploads
     */
    public function handleNewAttachments(Message $message, ?array $attachments, string $ownerId): void
    {
        if (empty($attachments)) {
            $message->setAttachments(null);

            return;
        }

        $thread = $message->getThread() ?? throw new \LogicException('The message has no thread');

        $workspace = null;
        $result = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment) || !is_string($attachment['type'] ?? null)) {
                throw new BadRequestHttpException('Invalid message attachment');
            }

            if (self::TYPE_FILE !== $attachment['type']) {
                $result[] = $attachment;
                continue;
            }

            // File attachments can only come from an upload: a posted File id
            // would give access to any file.
            $multipart = $attachment['multipart'] ?? null;
            if (!is_array($multipart)) {
                throw new BadRequestHttpException('A file attachment must provide its "multipart" upload');
            }

            $workspace ??= $this->getThreadWorkspace($thread);
            $upload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromArray($multipart), $ownerId);

            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setType($upload->getType());
            $file->setExtension(FileUtil::guessExtension($upload->getType(), $upload->getFilename()));
            $file->setSize($upload->getSize());
            $file->setOriginalName($upload->getFilename());
            $file->setPath($upload->getPath());
            $this->em->persist($file);
            $message->addFile($file);

            $result[] = [
                'type' => self::TYPE_FILE,
                'content' => json_encode([
                    'id' => $file->getId(),
                    'name' => $upload->getFilename(),
                    'type' => $upload->getType(),
                    'size' => $upload->getSize(),
                ], JSON_THROW_ON_ERROR),
            ];
        }

        $message->setAttachments($result);
    }

    /**
     * Removes the attachments whose content `id` is listed. Their files, unlinked
     * from the message, are removed unless something else uses them.
     */
    public function removeAttachments(Message $message, array $ids): void
    {
        [$kept, $removed] = self::partitionAttachments($message->getAttachments() ?? [], $ids);

        $removedFileIds = [];
        foreach ($removed as $attachment) {
            if (self::TYPE_FILE === ($attachment['type'] ?? null)) {
                $removedFileIds[] = self::getAttachmentId($attachment);
            }
        }

        $unlinkedIds = [];
        foreach ($message->getFiles()->toArray() as $file) {
            if (in_array($file->getId(), $removedFileIds, true)) {
                $message->removeFile($file);
                $unlinkedIds[] = $file->getId();
            }
        }
        if ([] !== $unlinkedIds) {
            $this->postFlushStack->addBusMessage(new DeleteFilesIfOrphan($unlinkedIds));
        }

        $message->setAttachments(empty($kept) ? null : $kept);
    }

    /**
     * Splits the attachments into the kept ones and the ones whose content
     * `id` is listed.
     *
     * @param string[] $ids
     *
     * @return array{0: array, 1: array}
     */
    public static function partitionAttachments(array $attachments, array $ids): array
    {
        $kept = [];
        $removed = [];
        foreach ($attachments as $attachment) {
            $id = self::getAttachmentId($attachment);
            if (null !== $id && in_array($id, $ids, true)) {
                $removed[] = $attachment;
            } else {
                $kept[] = $attachment;
            }
        }

        return [$kept, $removed];
    }

    private static function getAttachmentId(mixed $attachment): ?string
    {
        if (!is_array($attachment) || !is_string($attachment['content'] ?? null)) {
            return null;
        }
        $data = json_decode($attachment['content'], true);
        $id = is_array($data) ? ($data['id'] ?? null) : null;

        return is_string($id) || is_int($id) ? (string) $id : null;
    }

    /**
     * Adds the (signed) download URL to the file attachments.
     */
    public function resolveAttachments(?array $attachments, ?Thread $thread): ?array
    {
        if (empty($attachments) || null === $thread) {
            return $attachments;
        }

        $workspaceId = false;

        return array_map(function (mixed $attachment) use ($thread, &$workspaceId): mixed {
            if (!is_array($attachment) || self::TYPE_FILE !== ($attachment['type'] ?? null)) {
                return $attachment;
            }

            $data = json_decode((string) ($attachment['content'] ?? ''), true);
            $file = is_array($data) && is_string($data['id'] ?? null) ? $this->em->find(File::class, $data['id']) : null;
            if (!$file instanceof File) {
                return $attachment;
            }

            if (false === $workspaceId) {
                try {
                    $workspaceId = $this->getThreadWorkspace($thread)->getId();
                } catch (\Throwable) {
                    $workspaceId = null;
                }
            }
            // Only the files of the thread workspace are served
            if (null === $workspaceId || $file->getWorkspaceId() !== $workspaceId) {
                return $attachment;
            }

            $data['url'] = $this->fileUrlResolver->resolveUrl($file);

            return [
                ...$attachment,
                'content' => json_encode($data, JSON_THROW_ON_ERROR),
            ];
        }, $attachments);
    }

    private function getThreadWorkspace(Thread $thread): Workspace
    {
        $object = $this->discussionManager->getThreadObject($thread);
        $workspace = method_exists($object, 'getWorkspace') ? $object->getWorkspace() : null;
        if (!$workspace instanceof Workspace) {
            throw new BadRequestHttpException('Files cannot be attached to this thread');
        }

        return $workspace;
    }
}
