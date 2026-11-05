<?php

declare(strict_types=1);

namespace App\Service\Import;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\File\DeleteFileFromStorage;
use App\Consumer\Handler\Import\DispatchAssetImport;
use App\Entity\Core\AssetImport;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Service\Storage\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetImportManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private FileStorageManager $storageManager,
        private FilePathGenerator $filePathGenerator,
        private PostFlushStack $postFlushStack,
    ) {
    }

    /**
     * Stores the instructions file.
     *
     * @param string|resource $content
     *
     * @return string the storage path
     */
    public function storeContent(Workspace $workspace, string $format, $content): string
    {
        $path = $this->filePathGenerator->generatePath($workspace->getId(), $format);
        if (is_resource($content)) {
            $this->storageManager->storeStream($path, $content);
        } else {
            $this->storageManager->store($path, $content);
        }

        return $path;
    }

    /**
     * Creates the import of a stored instructions file, read once flushed.
     */
    public function createImport(
        Workspace $workspace,
        string $format,
        string $sourcePath,
        string $ownerId,
        ?Collection $collection = null,
        ?int $privacy = null,
        ?string $originalName = null,
        ?WorkspaceIntegration $integration = null,
    ): AssetImport {
        $import = new AssetImport();
        $import->setWorkspace($workspace);
        $import->setFormat($format);
        $import->setSourcePath($sourcePath);
        $import->setOwnerId($ownerId);
        $import->setCollection($collection);
        $import->setPrivacy($privacy);
        $import->setOriginalName($originalName);
        $import->setIntegration($integration);

        $this->em->persist($import);
        $this->postFlushStack->addBusMessage(new DispatchAssetImport($import->getId()));

        return $import;
    }

    /**
     * Deletes the instructions file of a removed import, once flushed.
     */
    public function scheduleFileDeletion(AssetImport $import): void
    {
        $this->postFlushStack->addBusMessage(new DeleteFileFromStorage([$import->getSourcePath()]));
    }
}
