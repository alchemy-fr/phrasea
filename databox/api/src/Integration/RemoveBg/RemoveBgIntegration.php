<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Integration\AbstractIntegration;
use App\Integration\AssetActionIntegrationInterface;
use App\Integration\AssetOperationIntegrationInterface;
use App\Storage\RenditionManager;
use App\Storage\RenditionPathGenerator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RemoveBgIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface, AssetActionIntegrationInterface
{
    private const ACTION_PROCESS = 'process';

    private RemoveBgClient $client;
    private string $cacheDir;
    private RenditionManager $renditionManager;
    private FileStorageManager $fileStorageManager;
    private RenditionPathGenerator $pathGenerator;
    private EntityManagerInterface $em;
    private FileUrlResolver $fileUrlResolver;

    public function __construct(
        RemoveBgClient $client,
        RenditionManager $renditionManager,
        FileStorageManager $fileStorageManager,
        string $cacheDir,
        RenditionPathGenerator $pathGenerator,
        EntityManagerInterface $em,
        FileUrlResolver $fileUrlResolver
    )
    {
        $this->client = $client;
        $this->cacheDir = $cacheDir;
        $this->renditionManager = $renditionManager;
        $this->fileStorageManager = $fileStorageManager;
        $this->pathGenerator = $pathGenerator;
        $this->em = $em;
        $this->fileUrlResolver = $fileUrlResolver;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('apiKey');
        $resolver->setDefaults([
            'processIncoming' => false,
        ]);
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        if (!$options['processIncoming']) {
            return;
        }

        $this->process($asset, $options);
    }

    public function handleAssetAction(string $action, Request $request, Asset $asset, array $options): Response
    {
        switch ($action) {
            case self::ACTION_PROCESS:
                $assetRendition = $this->process($asset, $options);

                return new JsonResponse([
                    'url' => $this->fileUrlResolver->resolveUrl($assetRendition->getFile()),
                ]);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function process(Asset $asset, array $options): AssetRendition
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

        $assetRendition = $this->renditionManager->createOrReplaceRendition(
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

        return $assetRendition;
    }

    public static function getName(): string
    {
        return 'remove.bg';
    }

    public static function getTitle(): string
    {
        return 'Remove BG';
    }
}
