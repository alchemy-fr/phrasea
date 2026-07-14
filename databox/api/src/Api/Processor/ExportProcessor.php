<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\StorageBundle\Util\FileUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Core\AssetRendition;
use App\Model\Export;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\FileUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExportProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly HttpClientInterface $zippyClient,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly AssetNameResolver $assetNameResolver,
    ) {
    }

    /**
     * @param Export $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Export
    {
        $this->validator->validate($data);

        $renditionIds = $data->renditions;
        $files = [];
        foreach ($data->assets as $assetId) {
            $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($assetId, [
                AssetRenditionRepository::OPT_DEFINITION_IDS => $renditionIds,
                AssetRenditionRepository::OPT_WITH_FILE => true,
            ]);

            /** @var AssetRendition[] $renditions */
            foreach ($renditions as $rendition) {
                $asset = $rendition->getAsset();
                if (!$this->isGranted(AbstractVoter::READ, $rendition)) {
                    continue;
                }

                $file = $rendition->getFile();
                $extension = FileUtil::getExtensionFromType($file->getType());
                $ext = $extension ? '.'.$extension : '';

                $assetName = $this->assetNameResolver->resolveNameAsString($asset);
                $renditionName = $rendition->getName();

                $files[] = [
                    'uri' => $this->fileUrlResolver->resolveUrl($file),
                    'path' => sprintf('%s-%s-%s%s', StringUtil::slugify($renditionName), StringUtil::slugify($assetName ?? ''), $assetId, $ext),
                ];
            }
        }

        if (empty($files)) {
            throw new NotFoundHttpException('No files to export');
        }

        $response = $this->zippyClient->request('POST', '/archives', [
            'json' => [
                'downloadFilename' => 'Databox-export',
                'files' => $files,
            ],
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $data->downloadUrl = $json['downloadUrl'];

        return $data;
    }
}
