<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Util\ExtensionUtil;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

class RenditionManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createFile(
        string $storage,
        string $path,
        ?string $type,
        ?int $size,
        ?string $originalName,
        Workspace $workspace
    ): File {
        $file = new File();
        $file->setStorage($storage);
        $file->setType($type);
        $file->setSize($size);
        $file->setPath($path);
        $file->setWorkspace($workspace);
        $file->setOriginalName($originalName);

        if ($originalName) {
            $file->setExtension(ExtensionUtil::getExtension($originalName));
        } elseif ($file->getType()) {
            $mimeTypes = new MimeTypes();
            $extensions = $mimeTypes->getExtensions($file->getType());
            if (!empty($extensions)) {
                $file->setExtension($extensions[0]);
            }
        }

        $this->em->persist($file);

        return $file;
    }

    public function createOrReplaceRendition(
        Asset $asset,
        RenditionDefinition $definition,
        string $storage,
        string $path,
        ?string $type,
        ?int $size,
        ?string $originalName
    ): AssetRendition {
        $file = $this->createFile(
            $storage,
            $path,
            $type,
            $size,
            $originalName,
            $asset->getWorkspace(),
        );

        if (null === $asset->getFile() && $definition->isUseAsOriginal()) {
            $asset->setFile($file);
            $this->em->persist($asset);
        }

        $rendition = $this->em->getRepository(AssetRendition::class)
            ->findOneBy([
                'asset' => $asset->getId(),
                'definition' => $definition->getId(),
            ]);

        if (!$rendition instanceof AssetRendition) {
            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($definition);
        } else {
            // TODO remove old rendition file
        }

        $rendition->setFile($file);
        $rendition->setReady(true);

        $this->em->persist($rendition);

        return $rendition;
    }

    public function getAssetFromId(string $id): ?Asset
    {
        return $this->em->find(Asset::class, $id);
    }

    public function getDefinitionFromId(Workspace $workspace, string $id): ?RenditionDefinition
    {
        return $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'id' => $id,
            ]);
    }

    public function getRenditionDefinitionByName(Workspace $workspace, string $name): RenditionDefinition
    {
        $definition = $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
        }

        return $definition;
    }

    public function getDefinitionFromName(Workspace $workspace, string $name): ?RenditionDefinition
    {
        return $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'name' => $name,
            ]);
    }
}
