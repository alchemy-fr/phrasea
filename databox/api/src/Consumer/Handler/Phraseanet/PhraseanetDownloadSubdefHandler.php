<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Phraseanet;

use App\Consumer\Handler\File\ImportFile;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Storage\RenditionManager;
use App\Util\DoctrineUtil;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class PhraseanetDownloadSubdefHandler
{
    public function __construct(
        private RenditionManager $renditionManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(PhraseanetDownloadSubdef $message): void
    {
        $permalink = $message->getPermalink();
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getAssetId());

        $this->logger->debug(sprintf('Handling subdef from Phraseanet for asset "%s"', $asset->getId()));

        $workspace = $asset->getWorkspace();
        if (empty($permalink)) {
            throw new \InvalidArgumentException('Empty Phraseanet permalink');
        }

        [$urlPart] = explode('?', $permalink, 2);

        try {
            $renditionDefinition = $this->renditionManager->getRenditionDefinitionByName($workspace, $message->getSubdefName());
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning($e->getMessage());

            return;
        }

        $rendition = $this->renditionManager->createOrReplaceRenditionByPath(
            $asset,
            $renditionDefinition,
            File::STORAGE_URL,
            $permalink,
            $message->getType(),
            $message->getSize(),
            basename($urlPart)
        );

        $this->em->flush();

        $this->messageBus->dispatch(new ImportFile($rendition->getFile()->getId()));
    }
}
