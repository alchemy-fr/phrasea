<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Validator\Exception\ValidationException;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AssetRendition;
use App\Model\Export;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\RenditionPermissionManager;
use App\Util\FileUtil;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class ExportAction extends AbstractController
{
    public function __construct(
        private readonly Client $zippyClient,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly RenditionPermissionManager $renditionPermissionManager,
    ) {
    }

    public function __invoke(Export $data, Request $request): Export
    {
        $user = $this->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupsIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $renditionIds = $data->renditions;
        $files = [];
        foreach ($data->assets as $assetId) {
            $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($assetId, [
                AssetRenditionRepository::OPT_DEFINITION_IDS => $renditionIds,
                AssetRenditionRepository::WITH_FILE => true,
            ]);

            foreach ($renditions as $rendition) {
                $asset = $rendition->getAsset();
                if (!$this->renditionPermissionManager->isGranted(
                    $asset,
                    $rendition->getDefinition()->getClass(),
                    $userId,
                    $groupsIds
                )) {
                    continue;
                }

                $file = $rendition->getFile();
                $extension = FileUtil::getExtensionFromType($file->getType());
                $ext = $extension ? '.'.$extension : '';
                $files[] = [
                    'uri' => $this->fileUrlResolver->resolveUrl($file),
                    'path' => sprintf('%s-%s-%s%s', $rendition->getName(), $asset->getTitle(), $assetId, $ext),
                ];
            }
        }

        $response = $this->zippyClient->request('POST', '/archives', [
            'json' => [
                'downloadFilename' => 'Databox-export',
                'files' => $files,
            ],
        ]);
        $json = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

        $data->downloadUrl = $json['downloadUrl'];

        return $data;
    }
}
