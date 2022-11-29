<?php

declare(strict_types=1);

namespace App\Integration\ToastUi;

use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractFileAction;
use App\Util\FileUtil;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TuiPhotoEditorIntegration extends AbstractFileAction
{
    private const ACTION_SAVE = 'save';
    private const ACTION_DELETE = 'delete';

    public function handleFileAction(string $action, Request $request, File $file, array $config): Response
    {
        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $config['workspaceIntegration'];

        switch ($action) {
            case self::ACTION_SAVE:
                $newFile = $this->saveFile($file, $request);

                $data = $this->integrationDataManager->storeData(
                    $wsIntegration,
                    $file,
                    self::DATA_FILE_ID,
                    $newFile->getId(),
                    $request->request->get('name', self::getName()),
                    true
                );

                return new JsonResponse($this->serializeData($data), 201, [], true);
            case self::ACTION_DELETE:
                $dataId = $request->request->get('id');
                if (!$dataId) {
                    throw new BadRequestHttpException(sprintf('Missing "id"'));
                }
                $this->integrationDataManager->deleteById($wsIntegration, $dataId);

                return new JsonResponse();
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    public function supportsFileActions(File $file, array $config): bool
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
