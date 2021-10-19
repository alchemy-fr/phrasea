<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\SubDefinition;
use App\Entity\Core\SubDefinitionSpec;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

class SubDefinitionManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createFile(
        string $path,
        string $type,
        int $size,
        Workspace $workspace
    ): File
    {
        $file = new File();
        $file->setType($type);
        $file->setSize($size);
        $file->setPath($path);
        $file->setWorkspace($workspace);

        $this->em->persist($file);

        return $file;
    }

    public function createSubDefinition(
        Asset $asset,
        SubDefinitionSpec $specification,
        string $path,
        string $type,
        int $size
    ): SubDefinition {
        $file = $this->createFile(
            $path,
            $type,
            $size,
            $asset->getWorkspace()
        );

        $subDef = new SubDefinition();
        $subDef->setFile($file);
        $subDef->setReady(true);
        $subDef->setAsset($asset);
        $subDef->setSpecification($specification);

        $this->em->persist($subDef);

        return $subDef;
    }

    public function getAssetFromId(string $id): ?Asset
    {
        return $this->em->find(Asset::class, $id);
    }

    public function getSpecFromId(Workspace $workspace, string $id): ?SubDefinitionSpec
    {
        return $this->em->getRepository(SubDefinitionSpec::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'id' => $id,
            ]);
    }

    public function getSpecFromName(Workspace $workspace, string $name): ?SubDefinitionSpec
    {
        return $this->em->getRepository(SubDefinitionSpec::class)
            ->findOneBy([
                'workspace' => $workspace->getId(),
                'name' => $name,
            ]);
    }
}
