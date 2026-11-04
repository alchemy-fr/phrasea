<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle\Notifier;

use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;
use Alchemy\NotifierBundle\Email\EmailLogoProviderInterface;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shows in the notification emails the logo of the global configuration, the
 * one the clients display in their header.
 */
final readonly class GlobalConfigEmailLogoProvider implements EmailLogoProviderInterface
{
    public const string LOGO_KEY = 'logo.src';

    public function __construct(
        private ConfiguratorEntryRepository $repository,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getLogoSrc(): ?string
    {
        try {
            /** @var ConfiguratorEntry|null $entry */
            $entry = $this->repository->findOneBy(['name' => self::LOGO_KEY]);
        } catch (DBALException $e) {
            // A missing logo must never prevent the email from being sent
            $this->logger->warning(sprintf('Cannot read the email logo from the global configuration: %s', $e->getMessage()));

            return null;
        }

        $src = trim($entry?->getValue() ?? '');

        return '' !== $src ? $src : null;
    }
}
