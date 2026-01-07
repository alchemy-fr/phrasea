<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationConfig;
use App\Entity\PublicationProfile;
use App\Entity\TermsConfig;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::preUpdate)]
readonly class DescriptionListener implements EventSubscriber
{
    public function __construct(private \HTMLPurifier $purifier)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->handle($args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->handle($args);
    }

    private function handle(PrePersistEventArgs|PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Publication
            || $entity instanceof Asset
        ) {
            $entity->setDescription($this->cleanHtml($entity->getDescription()));
        }

        if ($entity instanceof Publication
            || $entity instanceof PublicationProfile
        ) {
            $this->handleConfig($entity->getConfig());
        }
    }

    private function handleConfig(PublicationConfig $config): void
    {
        $config->setCopyrightText($this->cleanHtml($config->getCopyrightText()));

        $this->handleTermsConfig($config->getTerms());
        $this->handleTermsConfig($config->getDownloadTerms());
    }

    private function handleTermsConfig(TermsConfig $config): void
    {
        $config->setText($this->cleanHtml($config->getText()));
    }

    private function cleanHtml(?string $data): ?string
    {
        if (null === $data) {
            return null;
        }

        return $this->purifier->purify($data);
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
