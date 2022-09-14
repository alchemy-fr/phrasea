<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\AssetActionIntegrationInterface;
use App\Integration\AssetOperationIntegrationInterface;
use App\Integration\IntegrationDataManager;
use App\Integration\IntegrationDataTransformerInterface;
use App\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RemoveBgIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface, AssetActionIntegrationInterface, IntegrationDataTransformerInterface
{
    private const ACTION_PROCESS = 'process';

    private const DATA_FILE_ID = 'file_id';
    private const DATA_FILE_URL = 'file_url';

    private RemoveBgClient $client;
    private EntityManagerInterface $em;
    private FileUrlResolver $fileUrlResolver;
    private IntegrationDataManager $dataManager;
    private FileManager $fileManager;

    public function __construct(
        RemoveBgClient $client,
        FileManager $fileManager,
        EntityManagerInterface $em,
        FileUrlResolver $fileUrlResolver,
        IntegrationDataManager $dataManager
    )
    {
        $this->client = $client;
        $this->em = $em;
        $this->fileUrlResolver = $fileUrlResolver;
        $this->dataManager = $dataManager;
        $this->fileManager = $fileManager;
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
                $file = $this->process($asset, $options);

                return new JsonResponse([
                    'url' => $this->fileUrlResolver->resolveUrl($file),
                ]);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function process(Asset $asset, array $options): File
    {
        $sourceFile = $asset->getFile();
        $src = $this->client->getBgRemoved($sourceFile, $options['apiKey']);

        $bgRemFile = $this->fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $src,
            'image/png',
            'png',
            sprintf('%s-bg-removed.png', $sourceFile->getOriginalName() ?? $sourceFile->getId())
        );

        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $options['workspaceIntegration'];
        $this->dataManager->storeData($wsIntegration, $asset, self::DATA_FILE_ID, $bgRemFile->getId());

        $this->em->flush();

        return $bgRemFile;
    }

    public function transformData(IntegrationData $data): void
    {
        $file = $this->em->find(File::class, $data->getValue());
        $data->setValue($this->fileUrlResolver->resolveUrl($file));
        $data->setName(self::DATA_FILE_URL);
    }

    public function supportData(string $integrationName, string $dataKey): bool
    {
        return $integrationName === self::getName() && $dataKey === self::DATA_FILE_ID;
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
