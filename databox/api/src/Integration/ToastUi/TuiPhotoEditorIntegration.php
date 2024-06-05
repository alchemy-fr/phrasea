<?php

declare(strict_types=1);

namespace App\Integration\ToastUi;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Core\File;
use App\Integration\AbstractFileAction;
use App\Integration\AbstractIntegration;
use App\Integration\Action\FileActionsTrait;
use App\Integration\ActionsIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\PusherTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TuiPhotoEditorIntegration extends AbstractIntegration implements ActionsIntegrationInterface
{
    use PusherTrait;
    use FileActionsTrait;

    private const ACTION_SAVE = 'save';
    private const ACTION_DELETE = 'delete';

    public function handleAction(string $action, Request $request, IntegrationConfig $config): ?Response
    {
        $file = $this->getFile($request);

        switch ($action) {
            case self::ACTION_SAVE:
                $newFile = $this->saveFile($file, $request);

                $data = $this->integrationDataManager->storeData(
                    $config->getWorkspaceIntegration(),
                    $this->getStrictUser()->getId(),
                    $file,
                    self::DATA_FILE_ID,
                    $newFile->getId(),
                    $request->request->get('name', self::getName()),
                    true
                );

                $this->triggerFilePush(self::getName(), $file, [
                    'action' => 'save',
                    'id' => $data->getId(),
                ], direct: true);

                return $this->createNewDataResponse($data);
            case self::ACTION_DELETE:
                $dataId = $request->request->get('id');
                if (!$dataId) {
                    throw new BadRequestHttpException('Missing "id"');
                }
                $this->integrationDataManager->deleteById($config->getWorkspaceIntegration(), $dataId, $this->getStrictUser()->getId());

                $this->triggerFilePush(self::getName(), $file, [
                    'action' => 'delete',
                    'id' => $dataId,
                ], direct: true);

                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
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
