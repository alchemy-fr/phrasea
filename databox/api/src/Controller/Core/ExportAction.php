<?php

declare(strict_types=1);

namespace App\Controller\Core;

use ApiPlatform\Core\Validator\Exception\ValidationException;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AssetRendition;
use App\Model\Export;
use App\Repository\Core\AssetRenditionRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\MimeTypes;

class ExportAction
{
    private Client $client;
    private ValidatorInterface $validator;
    private EntityManagerInterface $em;
    private FileUrlResolver $fileUrlResolver;

    public function __construct(
        Client $zippyClient,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        FileUrlResolver $fileUrlResolver
    )
    {
        $this->client = $zippyClient;
        $this->validator = $validator;
        $this->em = $em;
        $this->fileUrlResolver = $fileUrlResolver;
    }

    public function __invoke(Export $data, Request $request): Export
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $renditionIds = $data->renditions;
        $files = [];
        $mimeTypes = new MimeTypes();
        foreach ($data->assets as $assetId) {
            $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($assetId, [
                AssetRenditionRepository::OPT_DEFINITION_IDS => $renditionIds,
                AssetRenditionRepository::WITH_FILE => true,
            ]);

            foreach ($renditions as $rendition) {
                $file = $rendition->getFile();
                $extensions = $mimeTypes->getExtensions($file->getType());
                $ext = isset($extensions[0]) ? '.'.$extensions[0] : '';
                $files[] = [
                    'uri' => $this->fileUrlResolver->resolveUrl($file),
                    'path' => sprintf('%s-%s-%s%s', $rendition->getName(), $rendition->getAsset()->getTitle(), $assetId, $ext),
                ];
            }
        }

        $response = $this->client->request('POST', '/archives', [
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
