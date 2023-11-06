<?php

declare(strict_types=1);

namespace App;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\AssetUrlGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;

class ZippyManager
{
    public function __construct(private readonly Client $client, private readonly EntityManagerInterface $em, private readonly AssetUrlGenerator $assetUrlGenerator)
    {
    }

    public function getDownloadUrl(Publication $publication): string
    {
        $hash = $this->getArchiveHash($publication);

        if (null === $publication->getZippyId() || $hash !== $publication->getZippyHash()) {
            return $this->em->transactional(function () use ($publication, $hash): string {
                /** @var Publication $publication */
                $publication = $this->em->find(Publication::class, $publication->getId(), LockMode::PESSIMISTIC_WRITE);

                $files = array_map(fn (Asset $asset): array => [
                    'path' => $asset->getOriginalName(),
                    'uri' => $this->assetUrlGenerator->generateAssetUrl($asset),
                ], $publication->getAssets()->getValues());

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

                $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                $publication->setZippyId($json['id']);
                $publication->setZippyHash($hash);
                $this->em->persist($publication);
                $this->em->flush();

                return $json['downloadUrl'];
            });
        } else {
            return $this->fetchDownloadUrlFromId($publication->getZippyId());
        }
    }

    private function getArchiveHash(Publication $publication): string
    {
        $parts = [
            $publication->isIncludeDownloadTermsInZippy() ? 'include_terms' : 'exclude_terms',
            $publication->getTitle(),
        ];

        foreach ($publication->getAssets() as $asset) {
            $parts[] = sprintf('%s-%s-%s',
                $asset->getSlug(),
                $asset->getOriginalName(),
                $asset->getSize()
            );
        }

        return md5(implode(',', $parts));
    }

    private function fetchDownloadUrlFromId(string $id): string
    {
        $response = $this->client->request('GET', '/archives/'.$id);
        $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $json['downloadUrl'];
    }
}
