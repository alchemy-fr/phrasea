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
use Symfony\Component\OptionsResolver\OptionsResolver;

class TuiPhotoEditorIntegration extends AbstractFileAction
{
    private const ACTION_SAVE = 'save';

    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    public function handleFileAction(string $action, Request $request, File $file, array $options): Response
    {
        switch ($action) {
            case self::ACTION_SAVE:
                $newFile = $this->saveFile($file, $request);

                /** @var WorkspaceIntegration $wsIntegration */
                $wsIntegration = $options['workspaceIntegration'];
                $this->integrationDataManager->storeData(
                    $wsIntegration,
                    $file,
                    self::DATA_FILE_ID,
                    $newFile->getId(),
                    $request->request->get('name', self::getName()),
                    true
                );

                return new JsonResponse();
            default:
                throw new InvalidArgumentException('Unsupported action');
        }
    }

    public function supportsFileActions(File $file, array $options): bool
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
