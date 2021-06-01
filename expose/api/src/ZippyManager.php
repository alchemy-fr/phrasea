<?php

declare(strict_types=1);

namespace App;

use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Security\AssetUrlGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;

class ZippyManager
{
    /**
     * @var EntityManager
     */
    private EntityManagerInterface $em;
    private Client $client;
    private AssetUrlGenerator $assetUrlGenerator;

    public function __construct(Client $client, EntityManagerInterface $em, AssetUrlGenerator $assetUrlGenerator)
    {
        $this->client = $client;
        $this->em = $em;
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    public function getDownloadUrl(Publication $publication): string
    {
        if (null === $publication->getZippyId()) {
            return $this->em->transactional(function () use ($publication): string {
                /** @var Publication $publication */
                $publication = $this->em->find(Publication::class, $publication->getId(), LockMode::PESSIMISTIC_WRITE);

                if (null !== $publication->getZippyId()) {
                    return $this->fetchDownloadUrlFromId($publication->getZippyId());
                }

                $files = array_map(function (PublicationAsset $pubAsset): array {
                    $asset = $pubAsset->getAsset();

                    $path = $asset->getOriginalName();

                    return [
                        'path' => $path,
                        'uri' => $this->assetUrlGenerator->generateAssetUrl($asset, false),
                    ];
                }, $publication->getAssets()->getValues());

                if ($publication->isIncludeDownloadTermsInZippy()
                    && null !== $termsUrl = $publication->getDownloadTerms()->getUrl()) {
                    $files[] = [
                        'path' => basename($termsUrl),
                        'uri' => $termsUrl,
                    ];
                }

                $response = $this->client->request('POST', '/archives', [
                    'json' => [
                        'downloadFilename' => $publication->getTitle() ?? 'publication-'.$publication->getId(),
                        'files' => $files,
                    ],
                ]);

                $json = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

                $publication->setZippyId($json['id']);
                $this->em->persist($publication);
                $this->em->flush();

                return $json['downloadUrl'];
            });
        } else {
            return $this->fetchDownloadUrlFromId($publication->getZippyId());
        }
    }

    private function fetchDownloadUrlFromId(string $id): string
    {
        $response = $this->client->request('GET', '/archives/'.$id);
        $json = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

        return $json['downloadUrl'];
    }
}
