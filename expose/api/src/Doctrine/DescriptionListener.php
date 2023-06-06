<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationConfig;
use App\Entity\PublicationProfile;
use App\Entity\TermsConfig;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use HTMLPurifier;

class DescriptionListener implements EventSubscriber
{
    private HTMLPurifier $purifier;

    public function __construct(HTMLPurifier $purifier)
    {
        $this->purifier = $purifier;
    }

    public function prePersist(PrePersistEventArgs|LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function preUpdate(PreUpdateEventArgs|LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    private function handle(PrePersistEventArgs|PreUpdateEventArgs|LifecycleEventArgs $args): void
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

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
