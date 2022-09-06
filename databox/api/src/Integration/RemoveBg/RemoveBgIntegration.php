<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Integration\AssetOperationIntegrationInterface;
use App\Storage\RenditionManager;
use App\Storage\RenditionPathGenerator;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RemoveBgIntegration implements AssetOperationIntegrationInterface
{
    private RemoveBgClient $client;
    private string $cacheDir;
    private RenditionManager $renditionManager;
    private FileStorageManager $fileStorageManager;
    private RenditionPathGenerator $pathGenerator;
    private EntityManagerInterface $em;

    public function __construct(
        RemoveBgClient $client,
        RenditionManager $renditionManager,
        FileStorageManager $fileStorageManager,
        string $cacheDir,
        RenditionPathGenerator $pathGenerator,
        EntityManagerInterface $em
    )
    {
        $this->client = $client;
        $this->cacheDir = $cacheDir;
        $this->renditionManager = $renditionManager;
        $this->fileStorageManager = $fileStorageManager;
        $this->pathGenerator = $pathGenerator;
        $this->em = $em;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('apiKey');
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $tmpFile = $this->cacheDir.'/cache.png';
        if (!file_exists($tmpFile)) {
            touch($tmpFile);
            if (!file_exists($tmpFile)) {
                throw new RuntimeException(sprintf('Cannot write into "%s"', $tmpFile));
            }

            $stream = $this->client->getBgRemoved($asset->getFile(), $options['apiKey']);
            file_put_contents($tmpFile, $stream);
        }

        $stream = fopen($tmpFile, 'r');

        $renditionName = 'remove_bg';

        $extension = 'png';
        $path = $this->pathGenerator->generatePath($asset->getWorkspaceId(), $extension);
        $this->fileStorageManager->storeStream($path, $stream);
        fclose($stream);

        $this->renditionManager->createOrReplaceRendition(
            $asset,
            $this->renditionManager->getRenditionDefinitionByName(
                $asset->getWorkspace(),
                $renditionName
            ),
            File::STORAGE_S3_MAIN,
            $path,
            'image/png',
            42, // TODO
            sprintf('remove-bg.%s', $extension),
        );

        $this->em->flush();
    }

    public static function getName(): string
    {
        return 'Remove.bg';
    }
}
