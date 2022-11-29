<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractFileAction;
use App\Integration\AssetOperationIntegrationInterface;
use App\Util\FileUtil;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoveBgIntegration extends AbstractFileAction implements AssetOperationIntegrationInterface
{
    private const ACTION_PROCESS = 'process';

    private RemoveBgClient $client;

    public function __construct(RemoveBgClient $client)
    {
        $this->client = $client;
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('apiKey')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->booleanNode('processIncoming')
                ->defaultFalse()
            ->end()
        ;
    }

    public function handleAsset(Asset $asset, array $config): void
    {
        $this->process($asset->getFile(), $config);
    }

    public function handleFileAction(string $action, Request $request, File $file, array $config): Response
    {
        switch ($action) {
            case self::ACTION_PROCESS:
                $file = $this->process($file, $config);

                return new JsonResponse([
                    'url' => $this->fileUrlResolver->resolveUrl($file),
                ]);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function process(File $file, array $config): File
    {
        $src = $this->client->getBgRemoved($file, $config['apiKey']);

        $bgRemFile = $this->fileManager->createFileFromPath(
            $file->getWorkspace(),
            $src,
            'image/png',
            'png',
            sprintf('%s-bg-removed.png', $file->getOriginalName() ?? $file->getId())
        );

        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $config['workspaceIntegration'];
        $this->integrationDataManager->storeData($wsIntegration, $file, self::DATA_FILE_ID, $bgRemFile->getId());

        $this->em->flush();

        return $bgRemFile;
    }

    public function supportsFileActions(File $file, array $config): bool
    {
        return $this->supportsFile($file);
    }

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return $config['processIncoming'] && $asset->getFile() && $this->supportsFile($asset->getFile());
    }

    private function supportsFile(File $file): bool
    {
        return FileUtil::isImageType($file->getType());
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
