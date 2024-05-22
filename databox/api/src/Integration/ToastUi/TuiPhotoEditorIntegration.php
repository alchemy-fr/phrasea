<?php

declare(strict_types=1);

namespace App\Integration\ToastUi;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Core\File;
use App\Integration\AbstractFileAction;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\PusherTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TuiPhotoEditorIntegration extends AbstractFileAction
{
    use PusherTrait;

    private const ACTION_SAVE = 'save';
    private const ACTION_DELETE = 'delete';

    public function handleFileAction(string $action, Request $request, File $file, IntegrationConfig $config): ?Response
    {
        switch ($action) {
            case self::ACTION_SAVE:
                $newFile = $this->saveFile($file, $request);

                $data = $this->integrationDataManager->storeData(
                    $config->getWorkspaceIntegration(),
                    $file,
                    FileActionsIntegrationInterface::DATA_FILE_ID,
                    $newFile->getId(),
                    $request->request->get('name', self::getName()),
                    true
                );

                $this->triggerFilePush($file, [
                    'action' => 'save',
                    'id' => $data->getId(),
                ], direct: true);

                return new JsonResponse($this->serializeData($data), 201, [], true);
            case self::ACTION_DELETE:
                $dataId = $request->request->get('id');
                if (!$dataId) {
                    throw new BadRequestHttpException('Missing "id"');
                }
                $this->integrationDataManager->deleteById($config->getWorkspaceIntegration(), $dataId);

                $this->triggerFilePush($file, [
                    'action' => 'delete',
                    'id' => $dataId,
                ], direct: true);

                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
    }

    public function supportsFileActions(File $file, IntegrationConfig $config): bool
    {
        return FileUtil::isImageType($file->getType());
    }

    public static function getName(): string
    {
        return 'tui.photo-editor';
    }

    public static function getTitle(): string
    {
        return 'Toast UI Photo Editor';
    }
}
